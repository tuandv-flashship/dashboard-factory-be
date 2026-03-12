<?php

namespace App\Containers\AppSection\User\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\User\Actions\CreateUserAction;
use App\Containers\AppSection\User\UI\API\Requests\CreateUserRequest;
use App\Containers\AppSection\User\UI\API\Transformers\UserAdminTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateUserController extends ApiController
{
    public function __invoke(CreateUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $payload = $request->validated();
        $roleIds = $payload['role_ids'] ?? [];
        unset($payload['role_ids']);

        $user = $action->run($payload, ...$roleIds);

        return Response::create($user, UserAdminTransformer::class)
            ->parseIncludes(['roles'])
            ->created();
    }
}
