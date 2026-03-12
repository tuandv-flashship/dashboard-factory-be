<?php

namespace App\Containers\AppSection\Authorization\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ListPermissionsTreeRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        return [
            'guard' => 'string|nullable',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('roles.index');
    }
}
