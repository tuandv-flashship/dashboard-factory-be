<?php

namespace App\Containers\AppSection\RequestLog\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\RequestLog\Actions\ListRequestLogsAction;
use App\Containers\AppSection\RequestLog\UI\API\Requests\ListRequestLogsRequest;
use App\Containers\AppSection\RequestLog\UI\API\Transformers\RequestLogTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListRequestLogsController extends ApiController
{
    public function __construct(
        private readonly ListRequestLogsAction $listRequestLogsAction,
    ) {
    }

    public function __invoke(ListRequestLogsRequest $request): JsonResponse
    {
        $logs = $this->listRequestLogsAction->run();

        return Response::create($logs, RequestLogTransformer::class)->ok();
    }
}
