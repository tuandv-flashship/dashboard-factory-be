<?php

namespace App\Containers\AppSection\Media\UI\API\Controllers;

use App\Containers\AppSection\Media\Actions\CreateMediaFolderAction;
use App\Containers\AppSection\Media\UI\API\Requests\CreateMediaFolderRequest;
use App\Containers\AppSection\Media\UI\API\Transformers\MediaFolderTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateMediaFolderController extends ApiController
{
    public function __invoke(CreateMediaFolderRequest $request, CreateMediaFolderAction $action): JsonResponse
    {
        $folder = $action->run(
            $request->input('name'),
            (int) $request->input('parent_id', 0),
            (int) $request->user()->getKey(),
            $request->input('color'),
        );

        return response()->json([
            'data' => (new MediaFolderTransformer())->transform($folder),
        ]);
    }
}
