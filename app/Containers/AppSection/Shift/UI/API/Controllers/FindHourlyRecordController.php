<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordTransformer;
use App\Containers\AppSection\Shift\UI\API\Requests\FindHourlyRecordRequest;
use App\Ship\Parents\Controllers\ApiController;
use Apiato\Support\Facades\Response;
use Illuminate\Http\JsonResponse;

/**
 * GET /v1/admin/hourly-records/{id}
 *
 * Returns a single hourly record with issues, department and shiftDetail
 * eager-loaded to support the TargetEstimator in the transformer.
 */
final class FindHourlyRecordController extends ApiController
{
    public function __invoke(FindHourlyRecordRequest $request): JsonResponse
    {
        $record = HourlyRecord::with([
            'issues',
            'department',
            'shiftDetail',
        ])->findOrFail($request->id);

        return Response::create($record, HourlyRecordTransformer::class)->ok();
    }
}
