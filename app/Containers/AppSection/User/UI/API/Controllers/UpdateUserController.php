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
            'status',
        ]);

        // Only touch the password when a new one is actually submitted.
        // Passing it as a sanitize() default would write NULL on every
        // profile update and lock the user out of the password grant.
        if ($request->filled('new_password')) {
            $data['password'] = $request->new_password;
        }

        $user = $action->run($request->user_id, $data);

        return Response::create($user, UserTransformer::class)->ok();
    }
}
