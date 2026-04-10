<?php

namespace App\Containers\AppSection\FplatformData\UI\API\Controllers;

use App\Containers\AppSection\FplatformData\Tasks\GetAllTeamsInventoryTask;
use App\Containers\AppSection\FplatformData\UI\API\Requests\GetAllTeamsInventoryRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * Get inventory (tồn đầu/cuối ngày) for ALL departments of current factory.
 *
 * Runs queries concurrently for all teams (DTF + DTG if PD).
 * Results are cached (5 min for today, 1 hour for historical).
 *
 * Public endpoint — no auth required, used by TV dashboards.
 */
final class GetAllTeamsInventoryController extends ApiController
{
    public function __construct(
        private readonly GetAllTeamsInventoryTask $task,
    ) {
    }

    public function __invoke(GetAllTeamsInventoryRequest $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());

        $result = $this->task->run($date);

        return response()->json(['data' => $result]);
    }
}
