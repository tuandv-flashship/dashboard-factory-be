<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Production\Models\HourlyRecordMachine;
use Illuminate\Support\Facades\Cache;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftDetailMachine;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\DB;

/**
 * Propagate kpi_per_hour changes to shift_details, shift_detail_machines,
 * and hourly_record_machines for today and future dates.
 *
 * Does NOT write to hourly_records.target — that field is reserved for
 * manual overrides only. Target is always computed on-the-fly by
 * TargetEstimator::effective() using the snapshot kpi_per_hour from
 * shift_details / hourly_record_machines.
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
     * Cascade department-level KPI to shift_details.
     *
     * Applies to per_person and per_machine_dtf departments only.
     * (per_machine_dtg uses machine-level KPI — see propagateMachineKpi)
     */
    public function propagateDepartmentKpi(int $departmentId, int $newKpi): void
    {
        $affectedDetails = ShiftDetail::query()
            ->where('department_id', $departmentId)
            ->whereHas('shift', fn ($q) => $q->where('date', '>=', now()->toDateString()))
            ->get();

        if ($affectedDetails->isEmpty()) {
            return;
        }

        // Batch update shift_details.kpi_per_hour
        $detailIds = $affectedDetails->pluck('id')->toArray();

        ShiftDetail::whereIn('id', $detailIds)
            ->update(['kpi_per_hour' => $newKpi]);

        // Flush all cache to ensure stale data is cleared
        Cache::flush();
    }

    /**
     * Cascade machine-level KPI to shift_detail_machines → shift_details → hourly_record_machines.
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
        });

        // Flush all cache to ensure stale data is cleared
        Cache::flush();
    }
}
