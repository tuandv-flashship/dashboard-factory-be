<?php

namespace App\Containers\AppSection\Shift\Tasks;


use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Support\TargetEstimator;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Batch update hourly records with manual overrides.
 *
 * Supports partial update of: kpi_minutes, target, staff_required, note.
 * When kpi_minutes changes → auto-recalculates kpi_hours and kpi_percent.
 * When target changes  → cascades hour_start_inventory for subsequent slots.
 *
 * hour_start_inventory[i] = day_start_inventory − Σ effectiveTarget[0..i-1]
 * effectiveTarget = target ?? (kpi_per_hour × kpi_percent / 100)
 */
final class UpdateHourlyStaffTask extends ParentTask
{
    public function run(array $records): void
    {
        if (empty($records)) {
            return;
        }

        // Collect all record IDs, then batch load
        $ids = collect($records)->pluck('id')->toArray();
        $hourlyRecords = HourlyRecord::findMany($ids)->keyBy('id');

        if ($hourlyRecords->isEmpty()) {
            return;
        }

        // Index input records by id for O(1) lookup
        $inputMap = collect($records)->keyBy('id');

        // Track which (shift_id, department_id) pairs need cascade recalculation
        $needsCascade = [];

        foreach ($hourlyRecords as $id => $hourlyRecord) {
            $input = $inputMap->get($id);
            if (!$input) {
                continue;
            }

            $updates = [];

            // ── kpi_minutes → auto-compute kpi_hours, kpi_percent ──
            if (array_key_exists('kpi_minutes', $input) && $input['kpi_minutes'] !== null) {
                $kpiMinutes = (int) $input['kpi_minutes'];
                $updates['kpi_minutes'] = $kpiMinutes;
                $updates['kpi_hours']   = round($kpiMinutes / 60, 2);
                $updates['kpi_percent'] = round($kpiMinutes / 60 * 100, 2);
            }

            // ── target → direct update, mark for cascade ──
            if (array_key_exists('target', $input)) {
                $updates['target'] = $input['target'];
                $key = "{$hourlyRecord->shift_id}_{$hourlyRecord->department_id}";
                $needsCascade[$key] = [
                    'shift_id'      => $hourlyRecord->shift_id,
                    'department_id' => $hourlyRecord->department_id,
                ];
            }

            // ── staff_required, note → direct update ──
            if (array_key_exists('staff_required', $input)) {
                $updates['staff_required'] = $input['staff_required'];
            }

            if (array_key_exists('note', $input)) {
                $updates['note'] = $input['note'];
            }

            if (!empty($updates)) {
                $hourlyRecord->update($updates);
            }
        }

        // ── Cascade: recalculate hour_start_inventory for affected departments ──
        foreach ($needsCascade as $pair) {
            $this->cascadeInventory($pair['shift_id'], $pair['department_id']);
        }
    }

    /**
     * Recalculate hour_start_inventory for all hourly records of a department.
     *
     * hour_start_inventory[i] = day_start_inventory − Σ effectiveTarget[0..i-1]
     * effectiveTarget = target > 0 ? target : (kpi_per_hour × kpi_percent / 100)
     */
    private function cascadeInventory(int $shiftId, int $departmentId): void
    {
        $detail = ShiftDetail::where('shift_id', $shiftId)
            ->where('department_id', $departmentId)
            ->first();

        if (!$detail) {
            return;
        }

        $dayStartInventory = $detail->day_start_inventory ?? 0;

        // Determine kpi_per_hour based on productivity type
        $dept = $detail->department;
        $isPerMachineDtg  = $dept?->productivity_type?->isPerMachineDtg() ?? false;
        $isPerMachineDtf  = $dept?->productivity_type?->isPerMachineDtf() ?? false;
        $kpiPerHour       = $isPerMachineDtg ? ($detail->kpi_per_hour ?? 0) : ($dept?->kpi_per_hour ?? 0);
        $defaultHeadcount = $detail->headcount ?? 0;
        $defaultMultiplier = $isPerMachineDtf ? ($detail->machine_count ?? 0) : $defaultHeadcount;

        $records = HourlyRecord::where('shift_id', $shiftId)
            ->where('department_id', $departmentId)
            ->orderBy('hour_index')
            ->get();

        $cumulativeTarget = 0;

        foreach ($records as $record) {
            $hourStartInventory = max(0, $dayStartInventory - $cumulativeTarget);

            if ($record->hour_start_inventory !== $hourStartInventory) {
                $record->update(['hour_start_inventory' => $hourStartInventory]);
            }

            $cumulativeTarget += TargetEstimator::effective(
                $record->target,
                $kpiPerHour,
                $record->kpi_percent ?? 100,
                $isPerMachineDtg,
                $record->staff_required ?? $defaultMultiplier,
            );
        }
    }
}
