<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Batch update staff for hourly records.
 * target is auto-recalculated: department.kpi_per_hour × staff.
 */
final class UpdateHourlyStaffTask extends ParentTask
{
    public function run(array $records): void
    {
        // Collect all record IDs upfront, then load departments in one query
        $hourlyRecords = HourlyRecord::findMany(
            collect($records)->pluck('id')->toArray()
        );

        $deptIds = $hourlyRecords->pluck('department_id')->unique();
        $departments = Department::whereIn('id', $deptIds)->get()->keyBy('id');

        foreach ($records as $record) {
            $hourlyRecord = $hourlyRecords->find($record['id']);
            if (!$hourlyRecord) {
                continue;
            }

            $dept = $departments->get($hourlyRecord->department_id);
            $kpiPerHour = $dept?->kpi_per_hour ?? 0;

            $staff = $record['staff'];
            $target = (int) round($kpiPerHour * $staff);

            $hourlyRecord->update([
                'staff'  => $staff,
                'target' => $target,
            ]);
        }
    }
}
