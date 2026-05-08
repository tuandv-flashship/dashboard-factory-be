<?php

namespace App\Containers\AppSection\User\UI\API\Transformers;

use App\Containers\AppSection\Authorization\UI\API\Transformers\PermissionTransformer;
use App\Containers\AppSection\Authorization\UI\API\Transformers\RoleTransformer;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;
use League\Fractal\Resource\Collection;

class UserTransformer extends ParentTransformer
{
    protected array $availableIncludes = [
        'roles',
        'permissions',
    ];

    protected array $defaultIncludes = [];

    public function transform(User $user): array
    {
        return [
            'type' => $user->getResourceKey(),
            'id' => $user->getHashedKey(),
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'gender' => $user->gender,
            'birth' => $user->birth,
            'phone' => $user->phone,
            'description' => $user->description,
            'status' => $user->status,
            'avatar_url' => $user->relationLoaded('avatar') && $user->avatar
                ? $user->avatar->preview_url
                : null,
        ];
    }

    public function includeRoles(User $user): Collection
    {
        $guard = config('auth.defaults.guard', 'api');
        $roles = $user->roles->where('guard_name', $guard);

        return $this->collection($roles, new RoleTransformer());
    }

    public function includePermissions(User $user): Collection
    {
        $guard = config('auth.defaults.guard', 'api');
        $permissions = $user->permissions->where('guard_name', $guard);

        return $this->collection($permissions, new PermissionTransformer());
    }
}
