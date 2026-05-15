<?php

namespace App\Containers\AppSection\Shift\Tasks;


use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\HourlyRecordMachine;
use App\Containers\AppSection\Production\Support\HourlyRecordChangeRecorder;
use App\Containers\AppSection\Production\Support\TargetEstimator;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\Auth;

/**
 * Batch update hourly records with manual overrides.
 *
 * Supports partial update of: kpi_minutes, target, staff_required, note,
 * machine_count, active_machine_ids.
 *
 * When kpi_minutes changes → auto-recalculates kpi_hours and kpi_percent.
 * When target changes  → cascades hour_start_inventory for subsequent slots.
 * When machine_count changes (DTF) → used as multiplier fallback for target.
 * When active_machine_ids changes (DTG) → syncs hourly_record_machines pivot,
 *   auto-updates machine_count and target from Σ(machine KPIs).
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
        $hourlyRecords = HourlyRecord::with('department')->findMany($ids)->keyBy('id');

        if ($hourlyRecords->isEmpty()) {
            return;
        }

        // Index input records by id for O(1) lookup
        $inputMap = collect($records)->keyBy('id');

        // Track which (shift_id, department_id) pairs need cascade recalculation
        $needsCascade = [];

        // Track records that need DTG machine pivot sync (post-loop)
        $machineSyncs = [];

        // ── Audit: cache auth info once (avoid repeated facade calls in loop) ──
        $auditUserId   = Auth::id() ?? 0;
        $auditUserName = Auth::user()?->name ?? 'System';
        $auditIp       = request()?->ip();

        foreach ($hourlyRecords as $id => $hourlyRecord) {
            $input = $inputMap->get($id);
            if (!$input) {
                continue;
            }

            // ── Audit: snapshot BEFORE update ──
            $oldSnap = HourlyRecordChangeRecorder::snapshot($hourlyRecord);

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

            // ── machine fields (per_machine departments only) ──
            $productivityType = $hourlyRecord->department?->productivity_type;

            // machine_count (DTF/DTG only) → direct update
            if (array_key_exists('machine_count', $input)
                && ($productivityType?->isPerMachineDtf() || $productivityType?->isPerMachineDtg())
            ) {
                $updates['machine_count'] = $input['machine_count'];
            }

            // active_machine_ids (DTG only) → defer pivot sync to post-loop
            if (array_key_exists('active_machine_ids', $input)
                && $productivityType?->isPerMachineDtg()
            ) {
                $machineSyncs[$id] = $input['active_machine_ids'];
            }

            if (!empty($updates)) {
                $hourlyRecord->update($updates);

                // ── Audit: record scalar changes ──
                HourlyRecordChangeRecorder::recordIfChanged(
                    $hourlyRecord,
                    $oldSnap,
                    $auditUserId,
                    $auditUserName,
                    $auditIp,
                );
            }
        }

        // ── Sync hourly_record_machines pivot (DTG per-slot machine overrides) ──
        foreach ($machineSyncs as $recordId => $machineIds) {
            $record = $hourlyRecords->get($recordId);
            if (!$record) {
                continue;
            }

            // ── Audit: snapshot BEFORE pivot sync ──
            $oldMachineNames = HourlyRecordChangeRecorder::snapshotMachineNames($record);
            $oldPivotSnap = [
                'machine_count' => $record->machine_count,
                'target'        => $record->target,
            ];

            // Delete old pivot rows
            HourlyRecordMachine::where('hourly_record_id', $recordId)->delete();

            if (empty($machineIds)) {
                $record->update(['machine_count' => 0]);

                // ── Audit: record machine changes (cleared all) ──
                HourlyRecordChangeRecorder::recordMachineChanges(
                    $record, $oldMachineNames, [], $oldPivotSnap,
                    $auditUserId,
                    $auditUserName,
                    $auditIp,
                );
                continue;
            }

            // Batch load machines, filter by department for safety
            $machines = Machine::whereIn('id', $machineIds)
                ->where('department_id', $record->department_id)
                ->get();

            $pivotRows = [];
            $totalKpi = 0;

            foreach ($machines as $machine) {
                $pivotRows[] = [
                    'hourly_record_id' => $recordId,
                    'machine_id'       => $machine->id,
                    'kpi_per_hour'     => $machine->kpi_per_hour,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
                $totalKpi += $machine->kpi_per_hour;
            }

            if (!empty($pivotRows)) {
                HourlyRecordMachine::insert($pivotRows);
            }

            // Auto-update machine_count + target (if no manual target provided)
            $autoUpdates = ['machine_count' => count($pivotRows)];

            $input = $inputMap->get($recordId, []);
            if (!array_key_exists('target', $input)) {
                $kpiPercent = $record->kpi_percent ?? 100;
                $autoUpdates['target'] = (int) round($totalKpi * $kpiPercent / 100);

                // Mark for inventory cascade
                $key = "{$record->shift_id}_{$record->department_id}";
                $needsCascade[$key] = [
                    'shift_id'      => $record->shift_id,
                    'department_id' => $record->department_id,
                ];
            }

            $record->update($autoUpdates);

            // ── Audit: record machine changes ──
            $newMachineNames = $machines->pluck('name')->sort()->values()->toArray();
            HourlyRecordChangeRecorder::recordMachineChanges(
                $record, $oldMachineNames, $newMachineNames, $oldPivotSnap,
                $auditUserId,
                $auditUserName,
                $auditIp,
            );
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
     *
     * NOTE: $defaultMultiplier only applies to DTF (uses machine_count as multiplier).
     * DTG ignores multiplier in TargetEstimator (isPerMachine=true → returns $base directly).
     * DTG target is pre-computed via pivot sync above, so TargetEstimator::effective()
     * returns the manual target for DTG slots.
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

        // Only DTF uses machine_count as multiplier; DTG multiplier is ignored by TargetEstimator
        $defaultTargetMultiplier = $isPerMachineDtf ? ($detail->machine_count ?? 0) : $defaultHeadcount;

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

            // DTF: target multiplier uses machine_count (staff_required is separate, display-only)
            // per_person: target multiplier = staff_required → headcount
            // DTG: multiplier is ignored by TargetEstimator (isPerMachine=true)
            $multiplier = $isPerMachineDtf
                ? ($record->machine_count ?? $defaultTargetMultiplier)
                : ($record->staff_required ?? $defaultHeadcount);

            $cumulativeTarget += TargetEstimator::effective(
                $record->target,
                $kpiPerHour,
                $record->kpi_percent ?? 100,
                $isPerMachineDtg,
                $multiplier,
            );
        }
    }
}
