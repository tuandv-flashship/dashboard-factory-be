<?php

namespace App\Containers\AppSection\System\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\System\Actions\GetSystemCacheStatusAction;
use App\Containers\AppSection\System\UI\API\Requests\GetSystemCacheStatusRequest;
use App\Containers\AppSection\System\UI\API\Transformers\SystemCacheStatusTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetSystemCacheStatusController extends ApiController
{
    public function __invoke(GetSystemCacheStatusRequest $request, GetSystemCacheStatusAction $action): JsonResponse
    {
        $payload = (object) $action->run();

        return Response::create()
            ->item($payload, SystemCacheStatusTransformer::class)
            ->ok();
    }
}
