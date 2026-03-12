<?php

namespace App\Containers\AppSection\RequestLog\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\RequestLog\Actions\DeleteAllRequestLogsAction;
use App\Containers\AppSection\RequestLog\UI\API\Requests\DeleteAllRequestLogsRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteAllRequestLogsController extends ApiController
{
    public function __construct(
        private readonly DeleteAllRequestLogsAction $deleteAllRequestLogsAction,
    ) {
    }

    public function __invoke(DeleteAllRequestLogsRequest $request): JsonResponse
    {
        $this->deleteAllRequestLogsAction->run();

        return Response::noContent();
    }
}
