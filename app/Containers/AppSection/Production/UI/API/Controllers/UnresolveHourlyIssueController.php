<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\UnresolveHourlyIssueAction;
use App\Containers\AppSection\Production\UI\API\Requests\UnresolveHourlyIssueRequest;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyIssueTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UnresolveHourlyIssueController extends ApiController
{
    public function __invoke(UnresolveHourlyIssueRequest $request): JsonResponse
    {
        $issue = app(UnresolveHourlyIssueAction::class)->run($request);

        return Response::create($issue, HourlyIssueTransformer::class)->ok();
    }
}
