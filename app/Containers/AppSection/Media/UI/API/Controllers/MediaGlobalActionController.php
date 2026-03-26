<?php

namespace App\Containers\AppSection\Media\UI\API\Controllers;

use App\Containers\AppSection\Media\Actions\MediaGlobalActionAction;
use App\Containers\AppSection\Media\UI\API\Requests\MediaGlobalActionRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class MediaGlobalActionController extends ApiController
{
    public function __invoke(MediaGlobalActionRequest $request, MediaGlobalActionAction $action): JsonResponse
    {
        $data = $action->run(
            $request->input('action'),
            $request->all(),
            (int) $request->user()->getKey(),
        );

        return response()->json([
            'data' => $data,
        ]);
    }
}
