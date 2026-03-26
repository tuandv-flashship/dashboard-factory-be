<?php

namespace App\Containers\AppSection\Media\UI\API\Controllers;

use App\Containers\AppSection\Media\Actions\DownloadMediaFileAction;
use App\Containers\AppSection\Media\UI\API\Responders\MediaFileOperationResponder;
use App\Containers\AppSection\Media\UI\API\Requests\DownloadMediaFileRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DownloadMediaFileController extends ApiController
{
    public function __invoke(
        DownloadMediaFileRequest $request,
        DownloadMediaFileAction $action,
        MediaFileOperationResponder $responder,
    ): JsonResponse
    {
        $input = $request->downloadInput();

        return $responder->respond($action->run(
            $input['url'],
            $input['folder_id'],
            (int) $request->user()->getKey(),
            $input['visibility'],
            $input['access_mode'],
        ));
    }
}
