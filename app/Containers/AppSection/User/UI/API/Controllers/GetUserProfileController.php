<?php

namespace App\Containers\AppSection\User\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\User\UI\API\Transformers\UserTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class GetUserProfileController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        $user = Auth::user();
        $user->load('avatar');

        return Response::create($user, UserTransformer::class)->ok();
    }
}
