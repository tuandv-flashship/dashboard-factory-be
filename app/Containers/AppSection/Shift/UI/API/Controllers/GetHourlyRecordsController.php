<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\UI\API\Requests\GetHourlyRecordsRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\HourlyRecordTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetHourlyRecordsController extends ApiController
{
    public function __invoke(GetHourlyRecordsRequest $request): JsonResponse
    {
        $records = HourlyRecord::with(['department', 'shiftDetail'])
            ->where('shift_id', $request->id)
            ->orderBy('department_id')
            ->orderBy('hour_index')
            ->get();

        return Response::create($records, HourlyRecordTransformer::class)->ok();
    }
}
