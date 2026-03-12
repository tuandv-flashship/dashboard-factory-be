<?php

namespace App\Containers\AppSection\AuditLog\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\AuditLog\Actions\DeleteAllAuditLogsAction;
use App\Containers\AppSection\AuditLog\UI\API\Requests\DeleteAllAuditLogsRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteAllAuditLogsController extends ApiController
{
    public function __construct(
        private readonly DeleteAllAuditLogsAction $deleteAllAuditLogsAction,
    ) {
    }

    public function __invoke(DeleteAllAuditLogsRequest $request): JsonResponse
    {
        $this->deleteAllAuditLogsAction->run();

        return Response::noContent();
    }
}
