<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class DeleteLastHourlyRecordRequest extends ParentRequest
{
    protected array $decode = ['shift_id', 'department_id'];

    protected array $urlParameters = ['shift_id', 'department_id'];

    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()->can('shifts.edit');
    }
}
