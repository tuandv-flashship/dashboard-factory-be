<?php

namespace App\Containers\AppSection\Shift\UI\API\Transformers;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ShiftDetailTransformer extends ParentTransformer
{
    protected array $defaultIncludes = [];
    protected array $availableIncludes = [];

    public function transform(ShiftDetail $detail): array
    {
        /** @var Department|null $dept */
        $dept = $detail->relationLoaded('department') ? $detail->department : null;
        $line = $dept?->relationLoaded('productionLine') ? $dept->productionLine : null;

        return [
            'id'                 => $detail->getHashedKey(),
            'department_id'      => $dept?->getHashedKey(),
            'department_code'    => $dept?->code,
            'department_label'   => $dept?->label,
            'line_code'          => $line?->code,
            'line_label'         => $line?->label,
            'shift_number'       => $detail->shift_number,
            'headcount'          => $detail->headcount,
            'kpi_per_hour'       => $detail->kpi_per_hour,
            'day_start_inventory'=> $detail->day_start_inventory,
            'start_time'         => $detail->start_time ? substr($detail->start_time, 0, 5) : null,
            'end_time'           => $detail->end_time,
            'work_hours'         => (float) $detail->work_hours,
            'prep_minutes'       => $detail->prep_minutes,
            'break1_start'       => $detail->break1_start ? substr($detail->break1_start, 0, 5) : null,
            'break1_minutes'     => $detail->break1_minutes,
            'meal_break_start'   => $detail->meal_break_start ? substr($detail->meal_break_start, 0, 5) : null,
            'meal_break_minutes' => $detail->meal_break_minutes,
            'break2_start'       => $detail->break2_start ? substr($detail->break2_start, 0, 5) : null,
            'break2_minutes'     => $detail->break2_minutes,
            'break3_start'       => $detail->break3_start ? substr($detail->break3_start, 0, 5) : null,
            'break3_minutes'     => $detail->break3_minutes,
        ];
    }
}
