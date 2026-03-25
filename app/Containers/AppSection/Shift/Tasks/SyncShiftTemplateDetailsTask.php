<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class SyncShiftTemplateDetailsTask extends ParentTask
{
    /**
     * Delete existing details and recreate from the given array.
     *
     * @param  int   $shiftTemplateId
     * @param  array $details  Array of detail rows
     */
    public function run(int $shiftTemplateId, array $details): void
    {
        // Remove all existing details
        ShiftTemplateDetail::where('shift_template_id', $shiftTemplateId)->delete();

        // Recreate
        foreach ($details as $detail) {
            ShiftTemplateDetail::create([
                'shift_template_id' => $shiftTemplateId,
                'department_id'     => $detail['department_id'],
                'shift_number'      => $detail['shift_number'],
                'headcount'         => $detail['headcount'] ?? 0,
                'start_time'        => $detail['start_time'],
                'work_hours'        => $detail['work_hours'],
                'prep_minutes'      => $detail['prep_minutes'] ?? 0,
                'break1_start'      => $detail['break1_start'] ?? null,
                'break1_minutes'    => $detail['break1_minutes'] ?? 0,
                'meal_break_start'  => $detail['meal_break_start'] ?? null,
                'meal_break_minutes'=> $detail['meal_break_minutes'] ?? 0,
                'break2_start'      => $detail['break2_start'] ?? null,
                'break2_minutes'    => $detail['break2_minutes'] ?? 0,
                'break3_start'      => $detail['break3_start'] ?? null,
                'break3_minutes'    => $detail['break3_minutes'] ?? 0,
            ]);
        }
    }
}
