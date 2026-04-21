<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\ResolveHourlyIssueAction;
use App\Containers\AppSection\Production\UI\API\Requests\ResolveHourlyIssueRequest;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyIssueTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ResolveHourlyIssueController extends ApiController
{
    public function __invoke(ResolveHourlyIssueRequest $request): JsonResponse
    {
        $issue = app(ResolveHourlyIssueAction::class)->run($request);

        return Response::create($issue, HourlyIssueTransformer::class)->ok();
    }
}
