<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CreateShiftRequest extends ParentRequest
{
    protected array $decode = ['shift_template_id'];

    public function rules(): array
    {
        return [
            // Accepts: "2026-05-04" (string) or ["2026-05-04","2026-05-05"] (array)
            'date'              => 'required',
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

    /**
     * Custom validation: `date` must be a valid date string or an array of valid date strings.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $date = $this->input('date');

            if (is_string($date)) {
                if (!strtotime($date)) {
                    $v->errors()->add('date', 'The date is not a valid date.');
                }
            } elseif (is_array($date)) {
                if (empty($date)) {
                    $v->errors()->add('date', 'At least 1 date is required.');
                }
                foreach ($date as $i => $d) {
                    if (!is_string($d) || !strtotime($d)) {
                        $v->errors()->add("date.{$i}", "The date.{$i} is not a valid date.");
                    }
                }
            } else {
                $v->errors()->add('date', 'The date must be a string or an array of strings.');
            }
        });
    }

    public function authorize(): bool
    {
        return $this->user()?->can('shifts.create') ?? false;
    }
}
