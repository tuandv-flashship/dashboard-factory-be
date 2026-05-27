<?php

namespace App\Containers\AppSection\Authentication\UI\API\Requests\WebClient;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class IssueTokenRequest extends ParentRequest
{
    protected array $decode = [];

    /**
     * Login must be accessible without authentication.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => 'required',
        ];
    }
}
