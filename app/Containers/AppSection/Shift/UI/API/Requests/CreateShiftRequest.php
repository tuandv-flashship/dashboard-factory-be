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
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('shifts.create');
    }
}
