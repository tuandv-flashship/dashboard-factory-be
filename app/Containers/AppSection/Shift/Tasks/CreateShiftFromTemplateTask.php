<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Copy shift_template_details → shift_details for a given shift.
 */
final class CreateShiftFromTemplateTask extends ParentTask
{
    public function run(Shift $shift, int $templateId): void
    {
        $templateDetails = ShiftTemplateDetail::where('shift_template_id', $templateId)->get();

        foreach ($templateDetails as $td) {
            ShiftDetail::create([
                'shift_id'           => $shift->id,
                'department_id'      => $td->department_id,
                'shift_number'       => $td->shift_number,
                'headcount'          => $td->headcount,
                'start_time'         => $td->start_time,
                'work_hours'         => $td->work_hours,
                'prep_minutes'       => $td->prep_minutes,
                'break1_start'       => $td->break1_start,
                'break1_minutes'     => $td->break1_minutes,
                'meal_break_start'   => $td->meal_break_start,
                'meal_break_minutes' => $td->meal_break_minutes,
                'break2_start'       => $td->break2_start,
                'break2_minutes'     => $td->break2_minutes,
                'break3_start'       => $td->break3_start,
                'break3_minutes'     => $td->break3_minutes,
            ]);
        }
    }
}
