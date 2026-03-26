<?php

namespace App\Containers\AppSection\Media\UI\API\Controllers;

use App\Containers\AppSection\Media\Actions\ShowMediaFileAction;
use App\Containers\AppSection\Media\UI\API\Requests\ShowMediaFileRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ShowMediaFileController extends ApiController
{
    public function __invoke(
        ShowMediaFileRequest $request,
        ShowMediaFileAction $action,
    ): Response|BinaryFileResponse|RedirectResponse
    {
        return $action->run(
            (string) $request->route('hash'),
            (string) $request->route('id'),
            $request->hasValidSignature(),
            auth()->check(),
        );
    }
}
