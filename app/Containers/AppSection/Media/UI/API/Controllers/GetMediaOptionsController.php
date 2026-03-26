<?php

namespace App\Containers\AppSection\Media\UI\API\Controllers;

use App\Containers\AppSection\Media\Actions\GetMediaOptionsAction;
use App\Containers\AppSection\Media\UI\API\Requests\GetMediaOptionsRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetMediaOptionsController extends ApiController
{
    public function __invoke(GetMediaOptionsRequest $request, GetMediaOptionsAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->run(),
        ]);
    }
}
