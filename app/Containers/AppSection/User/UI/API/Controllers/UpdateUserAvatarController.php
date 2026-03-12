<?php

namespace App\Containers\AppSection\User\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\User\Actions\UpdateUserAvatarAction;
use App\Containers\AppSection\User\UI\API\Requests\UpdateUserAvatarRequest;
use App\Containers\AppSection\User\UI\API\Transformers\UserTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateUserAvatarController extends ApiController
{
    public function __invoke(UpdateUserAvatarRequest $request, UpdateUserAvatarAction $action): JsonResponse
    {
        $user = $action->run(
            $request->user_id,
            $request->file('avatar'),
        );

        return Response::create($user, UserTransformer::class)->ok();
    }
}
