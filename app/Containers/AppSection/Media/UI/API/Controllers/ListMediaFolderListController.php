<?php

namespace App\Containers\AppSection\Media\UI\API\Controllers;

use App\Containers\AppSection\Media\Actions\ListMediaFolderListAction;
use App\Containers\AppSection\Media\UI\API\Requests\ListMediaFolderListRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListMediaFolderListController extends ApiController
{
    public function __invoke(ListMediaFolderListRequest $request, ListMediaFolderListAction $action): JsonResponse
    {
        $parentId = (int) $request->input('parent_id', 0);
        $excludeIds = $request->input('exclude_ids', []);

        $data = $action->run($parentId, is_array($excludeIds) ? $excludeIds : []);

        return response()->json([
            'data' => $data,
        ]);
    }
}
