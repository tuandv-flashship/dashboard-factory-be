<?php

namespace App\Containers\AppSection\Machine\UI\API\Requests;

use App\Containers\AppSection\Machine\Enums\MachineStatus;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class UpdateMachineRequest extends ParentRequest
{
    protected array $decode = ['id'];

    protected array $urlParameters = ['id'];

    public function rules(): array
    {
        return [
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'code'          => ['sometimes', 'string', 'max:50'],
            'name'          => ['sometimes', 'string', 'max:255'],
            'description'   => ['sometimes', 'nullable', 'string', 'max:1000'],
            'unit'          => ['sometimes', 'string', 'max:50'],
            'kpi_per_hour'  => ['sometimes', 'integer', 'min:0'],
            'sort_order'    => ['sometimes', 'integer', 'min:0'],
            'is_active'     => ['sometimes', 'boolean'],
            'status'        => ['sometimes', Rule::enum(MachineStatus::class)],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }
}
