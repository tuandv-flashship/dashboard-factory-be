<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class UpdateHourlyStaffRequest extends ParentRequest
{
    protected array $decode = ['id'];

    public function rules(): array
    {
        return [
            'records'                  => 'required|array|min:1',
            'records.*.id'             => 'required|integer|exists:hourly_records,id',
            'records.*.kpi_minutes'    => 'sometimes|integer|min:1|max:60',
            'records.*.target'         => 'sometimes|nullable|integer|min:0',
            'records.*.staff_required' => 'sometimes|nullable|integer|min:0',
            'records.*.note'           => 'sometimes|nullable|string|max:500',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('shifts.edit');
    }
}
