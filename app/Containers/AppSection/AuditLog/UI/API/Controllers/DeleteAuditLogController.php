<?php

namespace App\Containers\AppSection\AuditLog\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\AuditLog\Actions\DeleteAuditLogAction;
use App\Containers\AppSection\AuditLog\UI\API\Requests\DeleteAuditLogRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteAuditLogController extends ApiController
{
    public function __construct(
        private readonly DeleteAuditLogAction $deleteAuditLogAction,
    ) {
    }

    public function __invoke(DeleteAuditLogRequest $request): JsonResponse
    {
        $id = (int) $request->route('audit_log_id');
        $this->deleteAuditLogAction->run($id);

        return Response::noContent();
    }
}
