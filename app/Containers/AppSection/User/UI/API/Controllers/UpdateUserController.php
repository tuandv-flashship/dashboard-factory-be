<?php

namespace App\Containers\AppSection\User\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\User\Actions\UpdateUserAction;
use App\Containers\AppSection\User\UI\API\Requests\UpdateUserRequest;
use App\Containers\AppSection\User\UI\API\Transformers\UserTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateUserController extends ApiController
{
    public function __invoke(UpdateUserRequest $request, UpdateUserAction $action): JsonResponse
    {
        $data = $request->sanitize([
            'name',
            'gender',
            'birth',
            'phone',
            'description',
        ]);

        // Only touch the password when a new one is actually submitted.
        // Passing it as a sanitize() default would write NULL on every
        // profile update and lock the user out of the password grant.
        if ($request->filled('new_password')) {
            $data['password'] = $request->new_password;
        }

        // The policy also lets a user edit their own profile, so the account
        // management fields have to be gated separately. sanitize() reads raw
        // input rather than validated(), so the Rule::excludeIf() guards in the
        // request never reach this payload. Without these checks any user could
        // grant themselves an admin role or activate their own pending account.
        $canManageUsers = $request->user()?->can('users.edit') ?? false;

        if ($canManageUsers && $request->has('status')) {
            $data['status'] = $request->status;
        }

        $roleIds = null;
        if ($canManageUsers && $request->has('role_ids')) {
            $roleIds = $request->role_ids;
        }

        $user = $action->run($request->user_id, $data, $roleIds);

        return Response::create($user, UserTransformer::class)
            ->parseIncludes(['roles'])
            ->ok();
    }
}
