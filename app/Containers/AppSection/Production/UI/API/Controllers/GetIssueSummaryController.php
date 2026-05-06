<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Production\Actions\GetIssueSummaryAction;
use App\Containers\AppSection\Production\UI\API\Requests\GetIssueSummaryRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetIssueSummaryController extends ApiController
{
    public function __invoke(GetIssueSummaryRequest $request): JsonResponse
    {
        $summary = app(GetIssueSummaryAction::class)->run($request);

        return response()->json(['data' => $summary]);
    }
}
