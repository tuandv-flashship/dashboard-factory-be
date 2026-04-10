<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Batch update staff for hourly records.
 *
 * Per-person:  target = department.kpi_per_hour × staff
 * Per-machine: target = shift_detail.kpi_per_hour (fixed by machines, NOT × staff)
 *              staff for per_machine = number of operators (info only)
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

        // Pre-load shift_details for per_machine departments to get kpi_per_hour
        $perMachineDeptIds = $departments->filter(
            fn ($d) => $d->productivity_type === ProductivityType::PerMachine
        )->keys()->toArray();

        $shiftDetailKpis = [];
        if (!empty($perMachineDeptIds)) {
            $shiftIds = $hourlyRecords->pluck('shift_id')->unique()->toArray();
            $shiftDetailKpis = ShiftDetail::whereIn('shift_id', $shiftIds)
                ->whereIn('department_id', $perMachineDeptIds)
                ->get()
                ->keyBy(fn ($sd) => "{$sd->shift_id}_{$sd->department_id}")
                ->map->kpi_per_hour
                ->toArray();
        }

        foreach ($records as $record) {
            $hourlyRecord = $hourlyRecords->find($record['id']);
            if (!$hourlyRecord) {
                continue;
            }

            $dept = $departments->get($hourlyRecord->department_id);
            $isPerMachine = $dept?->productivity_type === ProductivityType::PerMachine;

            $staff = $record['staff'];

            if ($isPerMachine) {
                // Per-machine: target is fixed by machines, not by staff
                $sdKey = "{$hourlyRecord->shift_id}_{$hourlyRecord->department_id}";
                $target = $shiftDetailKpis[$sdKey] ?? 0;
            } else {
                // Per-person: target = kpi_per_hour × staff
                $kpiPerHour = $dept?->kpi_per_hour ?? 0;
                $target = (int) round($kpiPerHour * $staff);
            }

            $hourlyRecord->update([
                'staff'  => $staff,
                'target' => $target,
            ]);
        }
    }
}
