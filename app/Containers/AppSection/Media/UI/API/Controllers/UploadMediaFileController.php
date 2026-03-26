<?php

namespace App\Containers\AppSection\Media\UI\API\Controllers;

use App\Containers\AppSection\Media\Actions\UploadMediaFileAction;
use App\Containers\AppSection\Media\UI\API\Responders\MediaFileOperationResponder;
use App\Containers\AppSection\Media\UI\API\Requests\UploadMediaFileRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UploadMediaFileController extends ApiController
{
    public function __invoke(
        UploadMediaFileRequest $request,
        UploadMediaFileAction $action,
        MediaFileOperationResponder $responder,
    ): JsonResponse {
        return $responder->respond($action->run(
            $request->uploadInput(),
            $request->file('file'),
            (int) $request->user()->getKey(),
        ));
    }
}
