<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\HourlyRecordMachine;
use App\Containers\AppSection\Production\Support\TargetEstimator;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftDetailMachine;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\DB;

/**
 * Propagate kpi_per_hour changes to shift_details, shift_detail_machines,
 * hourly_records, and hourly_record_machines for today and future dates.
 *
 * Called when:
 *   - Department kpi_per_hour changes (per_person / per_machine_dtf)
 *   - Machine kpi_per_hour changes (per_machine_dtg)
 *
 * Past shifts (date < today) are NOT touched — their snapshots are preserved.
 */
final class PropagateKpiToShiftDetailsTask extends ParentTask
{
    /**
     * Cascade department-level KPI to shift_details + hourly_records.
     *
     * Applies to per_person and per_machine_dtf departments only.
     * (per_machine_dtg uses machine-level KPI — see propagateMachineKpi)
     */
    public function propagateDepartmentKpi(int $departmentId, int $newKpi): void
    {
        $affectedDetails = ShiftDetail::query()
            ->where('department_id', $departmentId)
            ->whereHas('shift', fn ($q) => $q->where('date', '>=', now()->toDateString()))
            ->with('department')
            ->get();

        if ($affectedDetails->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($affectedDetails, $newKpi) {
            // ── 1. Batch update shift_details.kpi_per_hour ──
            $detailIds = $affectedDetails->pluck('id')->toArray();

            ShiftDetail::whereIn('id', $detailIds)
                ->update(['kpi_per_hour' => $newKpi]);

            // ── 2. Recalc hourly_records target ──
            $this->recalcHourlyTargetsForDetails($affectedDetails, $newKpi);
        });
    }

    /**
     * Cascade machine-level KPI to shift_detail_machines → shift_details → hourly_record_machines → hourly_records.
     *
     * Applies to per_machine_dtg machines only.
     */
    public function propagateMachineKpi(int $machineId, int $newKpi): void
    {
        $today = now()->toDateString();

        // Single query to get all affected pivot records (avoids duplicate whereHas)
        $affectedPivots = ShiftDetailMachine::query()
            ->where('machine_id', $machineId)
            ->whereHas('shiftDetail.shift', fn ($q) => $q->where('date', '>=', $today))
            ->get(['id', 'shift_detail_id']);

        if ($affectedPivots->isEmpty()) {
            return;
        }

        $affectedPivotIds = $affectedPivots->pluck('id')->toArray();
        $affectedDetailIds = $affectedPivots->pluck('shift_detail_id')->unique()->toArray();

        DB::transaction(function () use ($machineId, $newKpi, $affectedPivotIds, $affectedDetailIds) {
            // ── Layer 1: Update shift_detail_machines.kpi_per_hour ──
            ShiftDetailMachine::whereIn('id', $affectedPivotIds)
                ->update(['kpi_per_hour' => $newKpi]);

            // ── Layer 2: Recalc shift_details.kpi_per_hour = Σ(machines kpi) ──
            // Single raw UPDATE with subquery instead of N separate queries
            $placeholders = implode(',', array_fill(0, count($affectedDetailIds), '?'));
            DB::update("
                UPDATE shift_details sd
                SET sd.kpi_per_hour = (
                    SELECT COALESCE(SUM(sdm.kpi_per_hour), 0)
                    FROM shift_detail_machines sdm
                    WHERE sdm.shift_detail_id = sd.id
                ),
                sd.updated_at = NOW()
                WHERE sd.id IN ({$placeholders})
            ", $affectedDetailIds);

            // ── Layer 3: Update hourly_record_machines.kpi_per_hour ──
            // Single query to get shift_id + department_id from affected details
            $affectedDetails = ShiftDetail::whereIn('id', $affectedDetailIds)
                ->get(['id', 'shift_id', 'department_id']);

            $affectedShiftIds = $affectedDetails->pluck('shift_id')->unique()->toArray();
            $affectedDepartmentIds = $affectedDetails->pluck('department_id')->unique()->toArray();

            HourlyRecordMachine::query()
                ->where('machine_id', $machineId)
                ->whereHas('hourlyRecord', function ($q) use ($affectedShiftIds, $affectedDepartmentIds) {
                    $q->whereIn('shift_id', $affectedShiftIds)
                      ->whereIn('department_id', $affectedDepartmentIds);
                })
                ->update(['kpi_per_hour' => $newKpi]);

            // ── Layer 4: Recalc hourly_records target (DTG: isPerMachine = true) ──
            foreach ($affectedDetails as $detail) {
                $this->recalcHourlyTargetsDtg($detail);
            }
        });
    }

    /**
     * Recalculate hourly_records.target for non-DTG departments.
     *
     * Only updates records where target is NULL or 0 (no manual override).
     * Uses batch update grouped by computed target to reduce query count.
     */
    private function recalcHourlyTargetsForDetails(
        \Illuminate\Support\Collection $details,
        int $newKpi,
    ): void {
        // Batch-load all hourly_records for affected details in one query
        $shiftDeptPairs = $details->map(fn ($d) => [
            'shift_id'      => $d->shift_id,
            'department_id' => $d->department_id,
        ]);

        $recordsQuery = HourlyRecord::query();
        $recordsQuery->where(function ($q) use ($shiftDeptPairs) {
            foreach ($shiftDeptPairs as $pair) {
                $q->orWhere(function ($sub) use ($pair) {
                    $sub->where('shift_id', $pair['shift_id'])
                        ->where('department_id', $pair['department_id']);
                });
            }
        });

        // Only records without manual target override
        $records = $recordsQuery
            ->where(function ($q) {
                $q->whereNull('target')->orWhere('target', 0);
            })
            ->get();

        if ($records->isEmpty()) {
            return;
        }

        // Index details by composite key for O(1) lookup
        // (same department_id can appear in multiple shifts with different headcount)
        $detailsMap = $details->keyBy(fn ($d) => "{$d->shift_id}_{$d->department_id}");

        // Group records by computed target value for batch UPDATE
        $updateGroups = [];

        foreach ($records as $record) {
            $detail = $detailsMap->get("{$record->shift_id}_{$record->department_id}");
            if (!$detail) {
                continue;
            }

            $isPerMachine = $detail->department?->productivity_type?->isPerMachine() ?? false;
            $staffRequired = $record->staff_required ?? $detail->headcount ?? 0;
            $kpiPercent = $record->kpi_percent ?? 100;

            $newTarget = TargetEstimator::estimate(
                $newKpi,
                $kpiPercent,
                $isPerMachine,
                $staffRequired,
            );

            $updateGroups[$newTarget][] = $record->id;
        }

        // Batch update: one query per unique target value
        foreach ($updateGroups as $target => $ids) {
            HourlyRecord::whereIn('id', $ids)->update(['target' => $target]);
        }
    }

    /**
     * Recalculate hourly_records.target for DTG (per_machine) departments.
     *
     * DTG target = Σ(hourly_record_machines.kpi_per_hour) × kpi_percent / 100
     */
    private function recalcHourlyTargetsDtg(ShiftDetail $detail): void
    {
        $records = HourlyRecord::query()
            ->where('shift_id', $detail->shift_id)
            ->where('department_id', $detail->department_id)
            ->where(function ($q) {
                $q->whereNull('target')->orWhere('target', 0);
            })
            ->get();

        if ($records->isEmpty()) {
            return;
        }

        // Batch-load all machine KPI sums in a single query
        $recordIds = $records->pluck('id')->toArray();
        $machineKpiSums = HourlyRecordMachine::query()
            ->selectRaw('hourly_record_id, SUM(kpi_per_hour) as total_kpi')
            ->whereIn('hourly_record_id', $recordIds)
            ->groupBy('hourly_record_id')
            ->pluck('total_kpi', 'hourly_record_id');

        // Group by computed target for batch UPDATE
        $updateGroups = [];

        foreach ($records as $record) {
            $totalMachineKpi = (int) ($machineKpiSums->get($record->id) ?? 0);
            $kpiPercent = $record->kpi_percent ?? 100;

            $newTarget = TargetEstimator::estimate(
                $totalMachineKpi,
                $kpiPercent,
                true,
                0,
            );

            $updateGroups[$newTarget][] = $record->id;
        }

        foreach ($updateGroups as $target => $ids) {
            HourlyRecord::whereIn('id', $ids)->update(['target' => $target]);
        }
    }
}
