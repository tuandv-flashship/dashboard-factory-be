<?php

namespace App\Containers\AppSection\Shift\UI\API\Transformers;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ShiftTemplateDetailTransformer extends ParentTransformer
{
    public function transform(ShiftTemplateDetail $detail): array
    {
        $dept = $detail->department;

        $data = [
            'id'                  => $detail->getHashedKey(),
            'department_id'       => $detail->department_id,
            'department_code'     => $dept?->code,
            'department_label'    => $dept?->label,
            'production_line'     => $dept?->productionLine?->label,
            'production_line_code'=> $dept?->productionLine?->code,
            'productivity_type'   => $dept?->productivity_type?->value,
            'kpi_per_hour'        => $dept?->kpi_per_hour,
            'day_start_inventory' => $detail->day_start_inventory ?? 0,
            'shift_number'        => $detail->shift_number,
            'headcount'           => $detail->headcount,
            'start_time'          => $detail->start_time ? substr($detail->start_time, 0, 5) : null,
            'work_hours'          => (float) $detail->work_hours,
            'prep_minutes'        => $detail->prep_minutes,
            'end_time'            => $detail->end_time,  // auto-computed accessor
            'break1_start'        => $detail->break1_start ? substr($detail->break1_start, 0, 5) : null,
            'break1_minutes'      => $detail->break1_minutes,
            'meal_break_start'    => $detail->meal_break_start ? substr($detail->meal_break_start, 0, 5) : null,
            'meal_break_minutes'  => $detail->meal_break_minutes,
            'break2_start'        => $detail->break2_start ? substr($detail->break2_start, 0, 5) : null,
            'break2_minutes'      => $detail->break2_minutes,
            'break3_start'        => $detail->break3_start ? substr($detail->break3_start, 0, 5) : null,
            'break3_minutes'      => $detail->break3_minutes,
        ];

        // Per-machine departments: include available machines for selection
        if ($dept?->productivity_type === ProductivityType::PerMachine
            && $dept->relationLoaded('machines')
        ) {
            $data['available_machines'] = $dept->machines
                ->where('is_active', true)
                ->map(fn ($m) => [
                    'id'           => $m->getHashedKey(),
                    'code'         => $m->code,
                    'name'         => $m->name,
                    'kpi_per_hour' => $m->kpi_per_hour,
                ])
                ->values()
                ->toArray();
        } else {
            $data['available_machines'] = [];
        }

        return $data;
    }
}
