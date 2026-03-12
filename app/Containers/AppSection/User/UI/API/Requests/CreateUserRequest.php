<?php

namespace App\Containers\AppSection\User\UI\API\Requests;

use App\Containers\AppSection\User\Enums\Gender;
use App\Containers\AppSection\User\Enums\UserStatus;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class CreateUserRequest extends ParentRequest
{
    protected array $decode = [
        'role_ids.*',
    ];

    public function rules(): array
    {
        return [
            'name' => 'required|min:2|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                Password::default(),
                'confirmed',
            ],
            'gender' => [Rule::enum(Gender::class), 'nullable'],
            'birth' => ['date', 'nullable'],
            'phone' => ['string', 'max:20', 'nullable'],
            'description' => ['string', 'max:500', 'nullable'],
            'status' => [Rule::enum(UserStatus::class), 'nullable'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['required', 'exists:roles,id'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('users.create');
    }
}
