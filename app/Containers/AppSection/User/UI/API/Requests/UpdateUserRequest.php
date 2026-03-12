<?php

namespace App\Containers\AppSection\User\UI\API\Requests;

use App\Containers\AppSection\Authorization\Enums\Role as RoleEnum;
use App\Containers\AppSection\User\Enums\Gender;
use App\Containers\AppSection\User\Enums\UserStatus;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateUserRequest extends ParentRequest
{
    protected array $decode = [
        'user_id',
    ];
    
    public function rules(): array
    {
        $isAdmin = $this->user()->hasRole(RoleEnum::SUPER_ADMIN);

        return [
            'name' => 'min:2|max:50',
            'gender' => [Rule::enum(Gender::class), 'nullable'],
            'birth' => ['date', 'nullable'],
            'phone' => ['string', 'max:20', 'nullable'],
            'description' => ['string', 'max:500', 'nullable'],
            'status' => [
                Rule::excludeIf(!$isAdmin),
                Rule::enum(UserStatus::class),
            ],
            'current_password' => [
                Rule::requiredIf(fn (): bool => !$isAdmin && !is_null($this->user()->password) && $this->filled('new_password')),
                'current_password:api',
            ],
            'new_password' => [
                Password::default(),
                'required_with:current_password',
            ],
            'new_password_confirmation' => 'required_with:new_password|same:new_password',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', [User::class, $this->user_id]);
    }
}
