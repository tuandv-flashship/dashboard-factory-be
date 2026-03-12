<?php

namespace App\Containers\AppSection\User\UI\API\Requests;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Requests\Request as ParentRequest;

final class UpdateUserAvatarRequest extends ParentRequest
{
    protected array $decode = [
        'user_id',
    ];

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'max:5120'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', [User::class, $this->user_id]);
    }
}
