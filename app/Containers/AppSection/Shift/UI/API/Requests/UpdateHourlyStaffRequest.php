<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class UpdateHourlyStaffRequest extends ParentRequest
{
    protected array $decode = ['id'];

    public function rules(): array
    {
        return [
            'records'         => 'required|array|min:1',
            'records.*.id'    => 'required|integer|exists:hourly_records,id',
            'records.*.staff' => 'required|numeric|min:0',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('shifts.edit');
    }
}
