<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CreateHourlyRecordRequest extends ParentRequest
{
    protected array $decode = ['shift_id', 'department_id'];

    protected array $urlParameters = ['shift_id', 'department_id'];

    public function rules(): array
    {
        return [
            'kpi_minutes'          => 'required|integer|min:1|max:60',
            'target'               => 'sometimes|nullable|integer|min:0',
            'staff_required'       => 'sometimes|nullable|integer|min:0',
            'note'                 => 'sometimes|nullable|string|max:500',
            'machine_count'        => 'sometimes|nullable|integer|min:0',
            'active_machine_ids'   => 'sometimes|array',
            'active_machine_ids.*' => 'integer|exists:machines,id',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('shifts.edit') ?? false;
    }
}
