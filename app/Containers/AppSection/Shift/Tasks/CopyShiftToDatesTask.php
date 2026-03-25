<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Copy a shift (header + details + hourly_records) to target dates.
 */
final class CopyShiftToDatesTask extends ParentTask
{
    public function run(Shift $source, array $targetDates): array
    {
        $createdShifts = [];

        foreach ($targetDates as $date) {
            // Check if shift already exists for this date + shift_number
            $exists = Shift::where('date', $date)
                ->where('shift_number', $source->shift_number)
                ->exists();

            if ($exists) {
                continue; // Skip if shift already assigned
            }

            // Clone shift header
            $newShift = Shift::create([
                'date'              => $date,
                'shift_number'      => $source->shift_number,
                'start_time'        => $source->start_time,
                'end_time'          => $source->end_time,
                'supervisor'        => $source->supervisor,
                'is_active'         => true,
                'shift_template_id' => $source->shift_template_id,
            ]);

            // Clone details
            $sourceDetails = ShiftDetail::where('shift_id', $source->id)->get();
            foreach ($sourceDetails as $detail) {
                ShiftDetail::create([
                    'shift_id'           => $newShift->id,
                    'department_id'      => $detail->department_id,
                    'shift_number'       => $detail->shift_number,
                    'headcount'          => $detail->headcount,
                    'start_time'         => $detail->start_time,
                    'work_hours'         => $detail->work_hours,
                    'prep_minutes'       => $detail->prep_minutes,
                    'break1_start'       => $detail->break1_start,
                    'break1_minutes'     => $detail->break1_minutes,
                    'meal_break_start'   => $detail->meal_break_start,
                    'meal_break_minutes' => $detail->meal_break_minutes,
                    'break2_start'       => $detail->break2_start,
                    'break2_minutes'     => $detail->break2_minutes,
                    'break3_start'       => $detail->break3_start,
                    'break3_minutes'     => $detail->break3_minutes,
                ]);
            }

            // Clone hourly records
            $sourceHourly = HourlyRecord::where('shift_id', $source->id)->get();
            $now = now();
            $hourlyRecords = [];

            foreach ($sourceHourly as $hr) {
                $hourlyRecords[] = [
                    'shift_id'      => $newShift->id,
                    'department_id' => $hr->department_id,
                    'hour_slot'     => $hr->hour_slot,
                    'hour_index'    => $hr->hour_index,
                    'staff'         => $hr->staff,
                    'target'        => $hr->target,
                    'actual'        => null,
                    'efficiency'    => 0,
                    'error_rate'    => 0,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            if (!empty($hourlyRecords)) {
                HourlyRecord::insert($hourlyRecords);
            }

            $createdShifts[] = $newShift;
        }

        return $createdShifts;
    }
}
