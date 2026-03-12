<?php

namespace App\Containers\AppSection\System\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\System\Actions\ClearSystemCacheAction;
use App\Containers\AppSection\System\UI\API\Requests\ClearSystemCacheRequest;
use App\Containers\AppSection\System\UI\API\Transformers\SystemCacheActionTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ClearSystemCacheController extends ApiController
{
    public function __invoke(ClearSystemCacheRequest $request, ClearSystemCacheAction $action): JsonResponse
    {
        $type = (string) $request->input('type');
        $payload = (object) $action->run($type);

        return Response::create()
            ->item($payload, SystemCacheActionTransformer::class)
            ->ok();
    }
}
