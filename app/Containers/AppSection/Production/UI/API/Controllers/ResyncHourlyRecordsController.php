<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Production\Tasks\SyncHourlyRecordsTask;
use App\Containers\AppSection\Production\UI\API\Requests\ResyncHourlyRecordsRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * POST /v1/admin/production/resync — manually trigger hourly records sync.
 *
 * Accepts optional ?date=YYYY-MM-DD&shift=N to target a specific shift.
 * Defaults to today's active shift if no params provided.
 * Clears all related production caches after sync.
 */
final class ResyncHourlyRecordsController extends ApiController
{
    public function __invoke(ResyncHourlyRecordsRequest $request): JsonResponse
    {
        $date          = $request->input('date');
        $shift         = $request->input('shift') ? (int) $request->input('shift') : null;
        $shiftDetailId = $request->input('shift_detail_id') ? (int) $request->input('shift_detail_id') : null;

        // Manual resync via API: force all departments when no specific dept is targeted
        $forceAll = $shiftDetailId === null;

        $result = app(SyncHourlyRecordsTask::class)->run($date, $shift, $shiftDetailId, $forceAll);

        if ($result['shift']) {
            ProductionCacheKeys::flushForShift($result['shift']);
        }

        return response()->json([
            'message' => $result['message'],
            'synced'  => $result['synced'],
        ], 202);
    }
}
