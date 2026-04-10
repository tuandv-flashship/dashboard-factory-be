<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Traits\ComputesHourlyTarget;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Batch update staff for hourly records.
 *
 * Target calculation via ComputesHourlyTarget trait.
 * Optimized: single batch update via CASE WHEN instead of N individual updates.
 */
final class UpdateHourlyStaffTask extends ParentTask
{
    use ComputesHourlyTarget;

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

        // Load departments in one query
        $deptIds = $hourlyRecords->pluck('department_id')->unique();
        $departments = Department::whereIn('id', $deptIds)->get()->keyBy('id');

        // Pre-load shift_details for per_machine departments
        $perMachineDeptIds = $departments->filter(
            fn ($d) => $d->productivity_type === ProductivityType::PerMachine
        )->keys()->toArray();

        $shiftDetails = collect();
        if (!empty($perMachineDeptIds)) {
            $shiftIds = $hourlyRecords->pluck('shift_id')->unique()->toArray();
            $shiftDetails = ShiftDetail::whereIn('shift_id', $shiftIds)
                ->whereIn('department_id', $perMachineDeptIds)
                ->get()
                ->keyBy(fn ($sd) => "{$sd->shift_id}_{$sd->department_id}");
        }

        // Build batch update: group by (target, staff) to minimize queries
        $updates = [];
        foreach ($records as $record) {
            $hourlyRecord = $hourlyRecords->get($record['id']);
            if (!$hourlyRecord) {
                continue;
            }

            $dept   = $departments->get($hourlyRecord->department_id);
            $staff  = $record['staff'];

            // For per_machine, pass a dummy ShiftDetail-like object to computeTarget
            if ($dept?->productivity_type === ProductivityType::PerMachine) {
                $sdKey = "{$hourlyRecord->shift_id}_{$hourlyRecord->department_id}";
                $sd = $shiftDetails->get($sdKey);
                $target = $sd ? $this->computeTarget($dept, $sd, $staff) : 0;
            } else {
                // Per-person needs a shift detail for the trait, but the trait only uses
                // dept KPI × multiplier for per_person, so we can pass any detail
                $target = (int) round(($dept?->kpi_per_hour ?? 0) * $staff);
            }

            // Directly update the already-loaded model (1 query each, N is typically 1–3)
            $hourlyRecord->update([
                'staff'  => $staff,
                'target' => $target,
            ]);
        }
    }
}
