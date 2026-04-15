<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Production\Tasks\SyncHourlyRecordsTask;
use App\Containers\AppSection\Production\UI\API\Requests\ResyncHourlyRecordsRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * POST /v1/admin/production/resync — manually trigger hourly records sync.
 *
 * Accepts optional ?date=YYYY-MM-DD&shift=N to target a specific shift.
 * Defaults to today's active shift if no params provided.
 * Clears the cached hourly response after sync.
 */
final class ResyncHourlyRecordsController extends ApiController
{
    public function __invoke(ResyncHourlyRecordsRequest $request): JsonResponse
    {
        $date = $request->input('date');
        $shift = $request->input('shift') ? (int) $request->input('shift') : null;

        $result = app(SyncHourlyRecordsTask::class)->run($date, $shift);

        // Clear cached hourly response so next GET reflects fresh data
        if ($result['shift']) {
            $resolvedDate = $result['shift']->date->toDateString();
            $resolvedShift = $result['shift']->shift_number;
            Cache::forget("all-lines-hourly:{$resolvedDate}:{$resolvedShift}");
        }

        return response()->json([
            'message' => $result['message'],
            'synced'  => $result['synced'],
        ]);
    }
}
