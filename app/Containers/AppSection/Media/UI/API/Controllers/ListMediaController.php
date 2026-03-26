<?php

namespace App\Containers\AppSection\Media\UI\API\Controllers;

use App\Containers\AppSection\Media\Actions\ListMediaAction;
use App\Containers\AppSection\Media\UI\API\Requests\ListMediaRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListMediaController extends ApiController
{
    public function __invoke(ListMediaRequest $request, ListMediaAction $action): JsonResponse
    {
        $data = $action->run($request->validated(), (int) $request->user()->getKey());

        return response()->json([
            'data' => $data,
        ]);
    }
}
