<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;

/**
 * Generate hourly_records from shift_details.
 * target = department.kpi_per_hour × staff (auto-computed).
 */
final class GenerateHourlyRecordsTask extends ParentTask
{
    public function run(Shift $shift): void
    {
        // Pre-load only required departments keyed by id for KPI lookup
        $shiftDetails = ShiftDetail::where('shift_id', $shift->id)->get();
        $deptIds = $shiftDetails->pluck('department_id')->unique();
        $departments = Department::whereIn('id', $deptIds)->get()->keyBy('id');

        $records = [];
        $now = now();

        // Track hour_index per department globally across all time blocks.
        // A department can have multiple ShiftDetail rows (e.g. morning + afternoon);
        // hour_index must be unique per (shift_id, department_id) to satisfy the DB constraint.
        $deptHourIndex = [];

        foreach ($shiftDetails as $detail) {
            $deptId = $detail->department_id;
            $dept = $departments->get($deptId);
            $kpiPerHour = $dept?->kpi_per_hour ?? 0;

            $hours = (int) floor($detail->work_hours);
            $start = Carbon::createFromFormat('H:i:s', $detail->start_time);

            for ($i = 0; $i < $hours; $i++) {
                $slotStart = $start->copy()->addHours($i);
                $slotEnd = $slotStart->copy()->addHour();
                $hourSlot = $slotStart->format('G') . 'h-' . $slotEnd->format('G') . 'h';

                $deptHourIndex[$deptId] = ($deptHourIndex[$deptId] ?? -1) + 1;

                $records[] = [
                    'shift_id'             => $shift->id,
                    'department_id'        => $deptId,
                    'hour_slot'            => $hourSlot,
                    'hour_index'           => $deptHourIndex[$deptId],
                    'staff'                => $detail->headcount,
                    'hour_start_inventory' => 0,
                    'target'               => (int) round($kpiPerHour * $detail->headcount),
                    'actual'               => null,
                    'efficiency'           => 0,
                    'error_rate'           => 0,
                    'status'               => HourlyRecordStatus::Pending->value,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }
        }

        if (!empty($records)) {
            HourlyRecord::insert($records);
        }
    }
}
