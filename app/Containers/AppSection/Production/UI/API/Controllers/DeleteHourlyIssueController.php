<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Production\Actions\DeleteHourlyIssueAction;
use App\Containers\AppSection\Production\UI\API\Requests\DeleteHourlyIssueRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteHourlyIssueController extends ApiController
{
    public function __invoke(DeleteHourlyIssueRequest $request): JsonResponse
    {
        app(DeleteHourlyIssueAction::class)->run($request);

        return response()->json(null, 204);
    }
}
