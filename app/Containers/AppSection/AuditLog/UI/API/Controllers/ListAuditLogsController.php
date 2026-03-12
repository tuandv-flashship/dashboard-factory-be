<?php

namespace App\Containers\AppSection\AuditLog\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\AuditLog\Actions\ListAuditLogsAction;
use App\Containers\AppSection\AuditLog\UI\API\Requests\ListAuditLogsRequest;
use App\Containers\AppSection\AuditLog\UI\API\Transformers\AuditLogTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListAuditLogsController extends ApiController
{
    public function __construct(
        private readonly ListAuditLogsAction $listAuditLogsAction,
    ) {
    }

    public function __invoke(ListAuditLogsRequest $request): JsonResponse
    {
        $logs = $this->listAuditLogsAction->run();

        return Response::create($logs, AuditLogTransformer::class)->ok();
    }
}
