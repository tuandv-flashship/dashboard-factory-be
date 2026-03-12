<?php

namespace App\Containers\AppSection\RequestLog\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\RequestLog\Actions\GetRequestLogWidgetAction;
use App\Containers\AppSection\RequestLog\UI\API\Requests\GetRequestLogWidgetRequest;
use App\Containers\AppSection\RequestLog\UI\API\Transformers\RequestLogTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetRequestLogWidgetController extends ApiController
{
    public function __construct(
        private readonly GetRequestLogWidgetAction $getRequestLogWidgetAction,
    ) {
    }

    public function __invoke(GetRequestLogWidgetRequest $request): JsonResponse
    {
        $logs = $this->getRequestLogWidgetAction->run();

        return Response::create($logs, RequestLogTransformer::class)->ok();
    }
}
