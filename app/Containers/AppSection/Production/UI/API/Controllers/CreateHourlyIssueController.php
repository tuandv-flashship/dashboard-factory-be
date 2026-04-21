<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\CreateHourlyIssueAction;
use App\Containers\AppSection\Production\UI\API\Requests\CreateHourlyIssueRequest;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyIssueTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateHourlyIssueController extends ApiController
{
    public function __invoke(CreateHourlyIssueRequest $request): JsonResponse
    {
        $issue = app(CreateHourlyIssueAction::class)->run($request);

        return Response::create($issue, HourlyIssueTransformer::class)->created();
    }
}
