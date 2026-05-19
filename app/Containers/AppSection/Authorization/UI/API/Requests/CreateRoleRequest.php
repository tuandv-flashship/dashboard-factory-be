<?php

namespace App\Containers\AppSection\Authorization\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CreateRoleRequest extends ParentRequest
{
    protected array $decode = [
        'permission_ids.*',
        'permission_scopes.*.permission_id',
        'permission_scopes.*.department_ids.*',
    ];
    
    
    public function rules(): array
    {
        return [
            'name' => 'required|unique:' . config('permission.table_names.roles') . ',name|min:2|max:20|regex:/^[a-zA-Z0-9_-]+$/',
            'description' => 'max:255',
            'display_name' => 'max:100',
            'permission_ids' => 'sometimes|array',
            'permission_ids.*' => 'required|exists:permissions,id',
            'permission_scopes'                    => 'sometimes|array',
            'permission_scopes.*.permission_id'    => 'required|exists:permissions,id',
            'permission_scopes.*.department_ids'    => 'required|array|min:1',
            'permission_scopes.*.department_ids.*'  => 'required|exists:departments,id',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('roles.create');
    }
}
