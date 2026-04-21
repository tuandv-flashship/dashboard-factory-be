<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\UpdateHourlyIssueAction;
use App\Containers\AppSection\Production\UI\API\Requests\UpdateHourlyIssueRequest;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyIssueTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateHourlyIssueController extends ApiController
{
    public function __invoke(UpdateHourlyIssueRequest $request): JsonResponse
    {
        $issue = app(UpdateHourlyIssueAction::class)->run($request);

        return Response::create($issue, HourlyIssueTransformer::class)->ok();
    }
}
