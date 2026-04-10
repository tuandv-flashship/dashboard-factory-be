<?php

namespace App\Containers\AppSection\Machine\UI\API\Requests;

use App\Containers\AppSection\Machine\Enums\MachineStatus;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class UpdateMachineStatusRequest extends ParentRequest
{
    protected array $access = [
        'permissions' => '',
        'roles' => '',
    ];

    /**
     * @return array<string, string[]>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(MachineStatus::class)],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }
}
