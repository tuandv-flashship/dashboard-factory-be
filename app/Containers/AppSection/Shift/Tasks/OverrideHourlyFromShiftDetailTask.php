<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\HourlyRecordMachine;
use App\Containers\AppSection\Production\Support\HourlyRecordChangeRecorder;
use App\Containers\AppSection\Production\Support\TargetEstimator;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\Auth;

/**
 * Cascade shift_detail values to ALL hourly_records of a department.
 *
 * Triggered only when the FE sends `override_hourly: true`
 * in the "Xác nhận nhân sự làm việc" form.
 *
 * Mapping:
 *   headcount     → staff_required  (all types)
 *   machine_count → machine_count   (per_machine_dtf)
 *   machine_ids   → pivot sync      (per_machine_dtg) + auto machine_count, target
 *
 * Each modified hourly_record gets its own audit log via HourlyRecordChangeRecorder.
 */
final class OverrideHourlyFromShiftDetailTask extends ParentTask
{
    /**
     * @param Shift       $shift
     * @param int         $departmentId
     * @param ShiftDetail $shiftDetail  Already updated shift detail
     * @param array       $data         Original request payload
     */
    public function run(Shift $shift, int $departmentId, ShiftDetail $shiftDetail, array $data): void
    {
        $records = HourlyRecord::with('department')
            ->where('shift_id', $shift->id)
            ->where('department_id', $departmentId)
            ->orderBy('hour_index')
            ->get();

        if ($records->isEmpty()) {
            return;
        }

        // ── Auth info (cached once) ──
        $auditUserId   = Auth::id() ?? 0;
        $auditUserName = Auth::user()?->name ?? 'System';
        $auditIp       = request()?->ip();

        $productivityType = $records->first()->department?->productivity_type;
        $isPerMachineDtg  = $productivityType?->isPerMachineDtg() ?? false;
        $isPerMachineDtf  = $productivityType?->isPerMachineDtf() ?? false;

        // ── 1. Scalar override: staff_required [+ machine_count for DTF] ──
        foreach ($records as $record) {
            $oldSnap = HourlyRecordChangeRecorder::snapshot($record);

            $updates = ['staff_required' => $shiftDetail->headcount];

            if ($isPerMachineDtf) {
                $updates['machine_count'] = $shiftDetail->machine_count;
            }

            $record->update($updates);

            HourlyRecordChangeRecorder::recordIfChanged(
                $record, $oldSnap,
                $auditUserId, $auditUserName, $auditIp,
            );
        }

        // ── 2. DTG machine pivot override ──
        if ($isPerMachineDtg && isset($data['machine_ids'])) {
            $machineIds = $data['machine_ids'];
            $machines = Machine::whereIn('id', $machineIds)
                ->where('department_id', $departmentId)
                ->get();

            $totalKpi = $machines->sum('kpi_per_hour');

            foreach ($records as $record) {
                $oldMachineNames = HourlyRecordChangeRecorder::snapshotMachineNames($record);
                $oldPivotSnap = [
                    'machine_count' => $record->machine_count,
                    'target'        => $record->target,
                ];

                // Delete old pivot
                HourlyRecordMachine::where('hourly_record_id', $record->id)->delete();

                // Insert new pivot
                $pivotRows = [];
                foreach ($machines as $machine) {
                    $pivotRows[] = [
                        'hourly_record_id' => $record->id,
                        'machine_id'       => $machine->id,
                        'kpi_per_hour'     => $machine->kpi_per_hour,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                }

                if (!empty($pivotRows)) {
                    HourlyRecordMachine::insert($pivotRows);
                }

                // Auto-update machine_count + target
                $kpiPercent = $record->kpi_percent ?? 100;
                $record->update([
                    'machine_count' => count($pivotRows),
                    'target'        => (int) round($totalKpi * $kpiPercent / 100),
                ]);

                // Audit machine changes
                $newMachineNames = $machines->pluck('name')->sort()->values()->toArray();
                HourlyRecordChangeRecorder::recordMachineChanges(
                    $record, $oldMachineNames, $newMachineNames, $oldPivotSnap,
                    $auditUserId, $auditUserName, $auditIp,
                );
            }

        }

        // ── 3. Cascade hour_start_inventory (reuse in-memory records) ──
        $this->cascadeInventory($records, $shiftDetail);
    }

    /**
     * Recalculate hour_start_inventory for all hourly records.
     *
     * hour_start_inventory[i] = day_start_inventory − Σ effectiveTarget[0..i-1]
     *
     * Reuses in-memory $records (already updated) to avoid redundant DB query.
     * Same formula as UpdateHourlyStaffTask::cascadeInventory().
     */
    private function cascadeInventory($records, ShiftDetail $detail): void
    {
        $dayStartInventory = $detail->day_start_inventory ?? 0;

        $dept = $detail->department;
        $isPerMachineDtg = $dept?->productivity_type?->isPerMachineDtg() ?? false;
        $isPerMachineDtf = $dept?->productivity_type?->isPerMachineDtf() ?? false;
        $kpiPerHour      = $isPerMachineDtg ? ($detail->kpi_per_hour ?? 0) : ($dept?->kpi_per_hour ?? 0);
        $defaultHeadcount = $detail->headcount ?? 0;

        // DTF: multiplier = machine_count; per_person: multiplier = headcount
        $defaultTargetMultiplier = $isPerMachineDtf ? ($detail->machine_count ?? 0) : $defaultHeadcount;

        $cumulativeTarget = 0;

        foreach ($records as $record) {
            $hourStartInventory = max(0, $dayStartInventory - $cumulativeTarget);

            if ($record->hour_start_inventory !== $hourStartInventory) {
                $record->update(['hour_start_inventory' => $hourStartInventory]);
            }

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
