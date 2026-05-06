<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\ListHourlyIssuesAction;
use App\Containers\AppSection\Production\UI\API\Requests\ListHourlyIssuesRequest;
use App\Containers\AppSection\Production\UI\API\Transformers\PendingIssueTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListHourlyIssuesController extends ApiController
{
    public function __invoke(ListHourlyIssuesRequest $request): JsonResponse
    {
        $issues = app(ListHourlyIssuesAction::class)->run($request);

        return Response::create($issues, PendingIssueTransformer::class)->ok();
    }
}
