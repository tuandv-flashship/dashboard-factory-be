<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CreateShiftRequest extends ParentRequest
{
    protected array $decode = ['shift_template_id'];

    public function rules(): array
    {
        return [
            'date'              => 'required|date',
            'shift_template_id' => 'required|integer|exists:shift_templates,id',
            'shift_numbers'     => 'required|array|min:1',
            'shift_numbers.*'   => 'required|integer|in:1,2',
            'supervisor'        => 'nullable|string|max:100',

            // Optional: FE có thể gửi kèm details đã chỉnh sửa (những cell trắng trong mockup).
            // Nếu không gửi → copy nguyên từ template.
            'details'                      => 'sometimes|array',
            'details.*.department_id'      => 'required_with:details|integer|exists:departments,id',
            'details.*.shift_number'       => 'required_with:details|integer|in:1,2',
            'details.*.headcount'          => 'sometimes|integer|min:0',
            'details.*.machine_count'      => 'sometimes|nullable|integer|min:0',
            'details.*.start_time'         => 'required_with:details|date_format:H:i',
            'details.*.work_hours'         => 'required_with:details|numeric|min:0|max:24',
            'details.*.prep_minutes'       => 'sometimes|integer|min:0',
            'details.*.break1_start'       => 'nullable|date_format:H:i',
            'details.*.break1_minutes'     => 'sometimes|integer|min:0',
            'details.*.meal_break_start'   => 'nullable|date_format:H:i',
            'details.*.meal_break_minutes' => 'sometimes|integer|min:0',
            'details.*.break2_start'       => 'nullable|date_format:H:i',
            'details.*.break2_minutes'     => 'sometimes|integer|min:0',
            'details.*.break3_start'       => 'nullable|date_format:H:i',
            'details.*.break3_minutes'     => 'sometimes|integer|min:0',
            'details.*.day_start_inventory'=> 'sometimes|integer|min:0',
            // Per-machine departments (e.g. DTG Print): FE gửi máy được chọn
            'details.*.machine_ids'        => 'sometimes|array',
            'details.*.machine_ids.*'      => 'integer|exists:machines,id',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('shifts.create');
    }
}
