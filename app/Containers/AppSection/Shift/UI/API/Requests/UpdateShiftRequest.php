<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class UpdateShiftRequest extends ParentRequest
{
    protected array $decode = ['id'];

    public function rules(): array
    {
        return [
            'supervisor'                  => 'sometimes|nullable|string|max:100',
            'is_active'                   => 'sometimes|boolean',
            'details'                     => 'sometimes|array',
            'details.*.department_id'     => 'required|integer|exists:departments,id',
            'details.*.shift_number'      => 'required|integer|in:1,2',
            'details.*.headcount'         => 'sometimes|integer|min:0',
            'details.*.start_time'        => 'required|date_format:H:i',
            'details.*.work_hours'        => 'required|numeric|min:0|max:24',
            'details.*.prep_minutes'      => 'sometimes|integer|min:0',
            'details.*.break1_start'      => 'nullable|date_format:H:i',
            'details.*.break1_minutes'    => 'sometimes|integer|min:0',
            'details.*.meal_break_start'  => 'nullable|date_format:H:i',
            'details.*.meal_break_minutes'=> 'sometimes|integer|min:0',
            'details.*.break2_start'      => 'nullable|date_format:H:i',
            'details.*.break2_minutes'    => 'sometimes|integer|min:0',
            'details.*.break3_start'      => 'nullable|date_format:H:i',
            'details.*.break3_minutes'    => 'sometimes|integer|min:0',
            // Per-machine departments (e.g. DTG Print): FE gửi máy được chọn
            'details.*.machine_ids'       => 'sometimes|array',
            'details.*.machine_ids.*'     => 'integer|exists:machines,id',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('shifts.edit');
    }
}
