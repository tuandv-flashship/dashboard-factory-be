<?php

namespace App\Containers\AppSection\Media\UI\API\Controllers;

use App\Containers\AppSection\Media\Actions\ListMediaFolderTreeAction;
use App\Containers\AppSection\Media\UI\API\Requests\ListMediaFolderTreeRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListMediaFolderTreeController extends ApiController
{
    public function __invoke(ListMediaFolderTreeRequest $request, ListMediaFolderTreeAction $action): JsonResponse
    {
        $excludeIds = $request->input('exclude_ids', []);

        $data = $action->run(is_array($excludeIds) ? $excludeIds : []);

        return response()->json([
            'data' => $data,
        ]);
    }
}
