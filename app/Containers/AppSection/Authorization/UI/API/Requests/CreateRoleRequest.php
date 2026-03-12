<?php

namespace App\Containers\AppSection\Authorization\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CreateRoleRequest extends ParentRequest
{
    protected array $decode = [
        'permission_ids.*',
    ];
    
    
    public function rules(): array
    {
        return [
            'name' => 'required|unique:' . config('permission.table_names.roles') . ',name|min:2|max:20|alpha',
            'description' => 'max:255',
            'display_name' => 'max:100',
            'permission_ids' => 'sometimes|array',
            'permission_ids.*' => 'required|exists:permissions,id',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('roles.create');
    }
}
