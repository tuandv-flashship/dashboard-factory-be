<?php

namespace App\Containers\AppSection\Authorization\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class UpdateRoleWithPermissionsRequest extends ParentRequest
{
    protected array $decode = [
        'role_id',
        'permission_ids.*',
    ];
    
    public function rules(): array
    {
        return [
            'role_id' => 'exists:roles,id',
            'display_name' => 'nullable|max:100',
            'description' => 'nullable|max:255',
            'permission_ids' => 'sometimes|array',
            'permission_ids.*' => 'required|exists:permissions,id',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('roles.edit');
    }
}
