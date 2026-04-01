<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Copy shift_template_details → shift_details for a given shift.
 *
 * Chỉ copy các details khớp với shift_number của shift đang tạo.
 * Nếu FE gửi kèm $overrides (details đã chỉnh sửa từ mockup),
 * thì merge override vào trước khi lưu.
 *
 * Override được key theo "department_id|shift_number".
 */
final class CreateShiftFromTemplateTask extends ParentTask
{
    public function run(Shift $shift, int $templateId, array $overrides = []): void
    {
        $templateDetails = ShiftTemplateDetail::where('shift_template_id', $templateId)
            ->where('shift_number', $shift->shift_number)
            ->get();

        // Index overrides by "department_id|shift_number" for O(1) lookup
        $overrideMap = collect($overrides)->keyBy(
            fn ($o) => "{$o['department_id']}|{$o['shift_number']}"
        );

        foreach ($templateDetails as $td) {
            $key      = "{$td->department_id}|{$td->shift_number}";
            $override = $overrideMap->get($key, []);

            ShiftDetail::create([
                'shift_id'           => $shift->id,
                'department_id'      => $td->department_id,
                'shift_number'       => $td->shift_number,
                // headcount: KHÔNG cho FE override — luôn copy từ template (cell màu vàng, read-only)
                'headcount'          => $td->headcount,
                'start_time'         => $override['start_time']         ?? $td->start_time,
                'work_hours'         => $override['work_hours']         ?? $td->work_hours,
                'prep_minutes'       => $override['prep_minutes']       ?? $td->prep_minutes,
                'break1_start'       => $override['break1_start']       ?? $td->break1_start,
                'break1_minutes'     => $override['break1_minutes']     ?? $td->break1_minutes,
                'meal_break_start'   => $override['meal_break_start']   ?? $td->meal_break_start,
                'meal_break_minutes' => $override['meal_break_minutes'] ?? $td->meal_break_minutes,
                'break2_start'       => $override['break2_start']       ?? $td->break2_start,
                'break2_minutes'     => $override['break2_minutes']     ?? $td->break2_minutes,
                'break3_start'       => $override['break3_start']       ?? $td->break3_start,
                'break3_minutes'     => $override['break3_minutes']     ?? $td->break3_minutes,
            ]);
        }
    }
}
