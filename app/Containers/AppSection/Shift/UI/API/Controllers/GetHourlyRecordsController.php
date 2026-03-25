<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\UI\API\Requests\GetHourlyRecordsRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\HourlyRecordTransformer;
use App\Ship\Parents\Controllers\ApiController;

final class GetHourlyRecordsController extends ApiController
{
    public function __invoke(GetHourlyRecordsRequest $request): array
    {
        $records = HourlyRecord::with('department')
            ->where('shift_id', $request->id)
            ->orderBy('department_id')
            ->orderBy('hour_index')
            ->get();

        return $this->transform($records, HourlyRecordTransformer::class);
    }
}
