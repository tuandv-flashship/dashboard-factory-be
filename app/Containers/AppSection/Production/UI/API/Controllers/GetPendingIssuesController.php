<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\GetPendingIssuesAction;
use App\Containers\AppSection\Production\UI\API\Requests\GetPendingIssuesRequest;
use App\Containers\AppSection\Production\UI\API\Transformers\PendingIssueTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetPendingIssuesController extends ApiController
{
    public function __invoke(GetPendingIssuesRequest $request): JsonResponse
    {
        $issues = app(GetPendingIssuesAction::class)->run($request);

        return Response::create($issues, PendingIssueTransformer::class)->ok();
    }
}
