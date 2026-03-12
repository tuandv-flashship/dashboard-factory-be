<?php

namespace App\Containers\AppSection\Machine\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

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
            'status' => ['required', 'string', 'in:online,offline,maintenance'],
        ];
    }

    public function authorize(): bool
    {
        return $this->check(['is_admin']);
    }
}
