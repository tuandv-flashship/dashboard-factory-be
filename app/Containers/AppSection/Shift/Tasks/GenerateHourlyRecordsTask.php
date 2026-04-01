<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Models\Department;
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
        // Pre-load departments keyed by id for KPI lookup
        $departments = Department::all()->keyBy('id');

        $shiftDetails = ShiftDetail::where('shift_id', $shift->id)->get();

        $records = [];
        $now = now();

        foreach ($shiftDetails as $detail) {
            $dept = $departments->get($detail->department_id);
            $kpiPerHour = $dept?->kpi_per_hour ?? 0;

            $hours = (int) floor($detail->work_hours);
            $start = Carbon::createFromFormat('H:i:s', $detail->start_time);

            for ($i = 0; $i < $hours; $i++) {
                $slotStart = $start->copy()->addHours($i);
                $slotEnd = $slotStart->copy()->addHour();
                $hourSlot = $slotStart->format('G') . 'h-' . $slotEnd->format('G') . 'h';

                $records[] = [
                    'shift_id'      => $shift->id,
                    'department_id' => $detail->department_id,
                    'hour_slot'     => $hourSlot,
                    'hour_index'    => $i,
                    'staff'         => $detail->headcount,
                    'hour_start_inventory' => 0,
                    'target'        => (int) round($kpiPerHour * $detail->headcount),
                    'actual'        => null,
                    'efficiency'    => 0,
                    'error_rate'    => 0,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        if (!empty($records)) {
            HourlyRecord::insert($records);
        }
    }
}
