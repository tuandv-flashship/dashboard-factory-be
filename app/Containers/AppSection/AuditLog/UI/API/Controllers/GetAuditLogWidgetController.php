<?php

namespace App\Containers\AppSection\AuditLog\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\AuditLog\Actions\GetAuditLogWidgetAction;
use App\Containers\AppSection\AuditLog\UI\API\Requests\GetAuditLogWidgetRequest;
use App\Containers\AppSection\AuditLog\UI\API\Transformers\AuditLogTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetAuditLogWidgetController extends ApiController
{
    public function __construct(
        private readonly GetAuditLogWidgetAction $getAuditLogWidgetAction,
    ) {
    }

    public function __invoke(GetAuditLogWidgetRequest $request): JsonResponse
    {
        $logs = $this->getAuditLogWidgetAction->run();

        return Response::create($logs, AuditLogTransformer::class)->ok();
    }
}
