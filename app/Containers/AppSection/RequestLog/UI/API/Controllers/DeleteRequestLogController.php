<?php

namespace App\Containers\AppSection\RequestLog\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\RequestLog\Actions\DeleteRequestLogAction;
use App\Containers\AppSection\RequestLog\UI\API\Requests\DeleteRequestLogRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteRequestLogController extends ApiController
{
    public function __construct(
        private readonly DeleteRequestLogAction $deleteRequestLogAction,
    ) {
    }

    public function __invoke(DeleteRequestLogRequest $request): JsonResponse
    {
        $id = (int) $request->route('request_log_id');
        $this->deleteRequestLogAction->run($id);

        return Response::noContent();
    }
}
