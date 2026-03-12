<?php

namespace App\Containers\AppSection\System\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\System\Actions\GetSystemAppSizeAction;
use App\Containers\AppSection\System\UI\API\Requests\GetSystemAppSizeRequest;
use App\Containers\AppSection\System\UI\API\Transformers\SystemAppSizeTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetSystemAppSizeController extends ApiController
{
    public function __invoke(GetSystemAppSizeRequest $request, GetSystemAppSizeAction $action): JsonResponse
    {
        $payload = (object) [
            'app_size' => $action->run(),
        ];

        return Response::create()
            ->item($payload, SystemAppSizeTransformer::class)
            ->ok();
    }
}
