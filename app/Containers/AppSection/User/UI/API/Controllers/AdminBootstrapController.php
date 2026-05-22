<?php

namespace App\Containers\AppSection\User\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Authorization\Models\Permission;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\UI\API\Transformers\UserTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class AdminBootstrapController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        $authenticatedUser = Auth::user();
        if ($authenticatedUser === null) {
            abort(401);
        }

        $guard = 'api';

        $user = User::query()
            ->findOrFail($authenticatedUser->getAuthIdentifier())
            ->load(['roles' => fn ($query) => $query->where('guard_name', $guard)]);

        $profileData = (new UserTransformer())->transform($user);
        $permissions = $user->isSuperAdmin()
            ? Permission::where('guard_name', $guard)->pluck('name')->values()->all()
            : $user->getAllPermissions()
                ->where('guard_name', $guard)
                ->pluck('name')
                ->values()
                ->all();
        $roles = $user->roles->pluck('name')->values()->all();

        return Response::create()->ok([
            'data' => [
                'user' => $profileData,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
        ]);
    }
}
