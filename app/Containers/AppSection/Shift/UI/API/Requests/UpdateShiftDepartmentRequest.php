<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class UpdateShiftDepartmentRequest extends ParentRequest
{
    protected array $decode = ['id'];

    public function rules(): array
    {
        return [
            'shift_number'      => 'sometimes|integer|in:1,2',
            'headcount'         => 'sometimes|integer|min:0',
            'machine_count'     => 'sometimes|nullable|integer|min:0',
            'start_time'        => 'sometimes|date_format:H:i',
            'work_hours'        => 'sometimes|numeric|min:0|max:24',
            'prep_minutes'      => 'sometimes|integer|min:0',
            'break1_start'      => 'nullable|date_format:H:i',
            'break1_minutes'    => 'sometimes|integer|min:0',
            'meal_break_start'  => 'nullable|date_format:H:i',
            'meal_break_minutes'=> 'sometimes|integer|min:0',
            'break2_start'      => 'nullable|date_format:H:i',
            'break2_minutes'    => 'sometimes|integer|min:0',
            'break3_start'      => 'nullable|date_format:H:i',
            'break3_minutes'    => 'sometimes|integer|min:0',
            // Per-machine departments: FE sends selected machines
            'machine_ids'       => 'sometimes|array',
            'machine_ids.*'     => 'integer|exists:machines,id',
            // Override flag: cascade shift_detail values to all hourly records
            'override_hourly'   => 'sometimes|boolean',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('shifts.edit') ?? false;
    }
}
