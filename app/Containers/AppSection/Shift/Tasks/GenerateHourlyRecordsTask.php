<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Traits\ComputesHourlyTarget;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;

/**
 * Generate hourly_records from shift_details.
 *
 * Optimized: single bulk insert, departments loaded via whereIn.
 * Target calculation delegated to ComputesHourlyTarget trait.
 */
final class GenerateHourlyRecordsTask extends ParentTask
{
    use ComputesHourlyTarget;

    public function run(Shift $shift): void
    {
        $shiftDetails = ShiftDetail::where('shift_id', $shift->id)->get();

        if ($shiftDetails->isEmpty()) {
            return;
        }

        $departments = Department::whereIn('id', $shiftDetails->pluck('department_id')->unique())
            ->get()
            ->keyBy('id');

        $records = [];
        $now = now();
        $deptHourIndex = [];

        foreach ($shiftDetails as $detail) {
            $deptId = $detail->department_id;
            $dept   = $departments->get($deptId);
            $target = $this->computeTarget($dept, $detail, $detail->headcount);

            $hours = (int) floor($detail->work_hours);
            $start = Carbon::createFromFormat('H:i:s', $detail->start_time);

            for ($i = 0; $i < $hours; $i++) {
                $slotStart = $start->copy()->addHours($i);
                $slotEnd   = $slotStart->copy()->addHour();

                $deptHourIndex[$deptId] = ($deptHourIndex[$deptId] ?? -1) + 1;

                $records[] = [
                    'shift_id'             => $shift->id,
                    'department_id'        => $deptId,
                    'hour_slot'            => $slotStart->format('G') . 'h-' . $slotEnd->format('G') . 'h',
                    'hour_index'           => $deptHourIndex[$deptId],
                    'staff'                => $detail->headcount,
                    'hour_start_inventory' => 0,
                    'target'               => $target,
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
