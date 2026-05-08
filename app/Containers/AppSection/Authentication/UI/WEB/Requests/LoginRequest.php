<?php

namespace App\Containers\AppSection\Authentication\UI\WEB\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class LoginRequest extends ParentRequest
{
    protected array $decode = [];

    
    
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => 'required',
            'remember' => 'boolean',
        ];
    }
}
