<?php

namespace App\Containers\AppSection\System\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\System\Actions\GetSystemInfoAction;
use App\Containers\AppSection\System\UI\API\Requests\GetSystemInfoRequest;
use App\Containers\AppSection\System\UI\API\Transformers\SystemInfoTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetSystemInfoController extends ApiController
{
    public function __invoke(GetSystemInfoRequest $request, GetSystemInfoAction $action): JsonResponse
    {
        $payload = (object) $action->run();

        return Response::create()
            ->item($payload, SystemInfoTransformer::class)
            ->ok();
    }
}
