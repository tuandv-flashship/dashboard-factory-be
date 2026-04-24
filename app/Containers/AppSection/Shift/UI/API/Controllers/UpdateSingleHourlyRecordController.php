<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordTransformer;
use App\Containers\AppSection\Shift\Actions\UpdateHourlyStaffAction;
use App\Containers\AppSection\Shift\UI\API\Requests\UpdateSingleHourlyRecordRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Apiato\Support\Facades\Response;

final class UpdateSingleHourlyRecordController extends ApiController
{
    public function __invoke(UpdateSingleHourlyRecordRequest $request): JsonResponse
    {
        // Reuse batch Task — wrap single record in array format
        $record = [
            'id' => $request->id,
            ...$request->validated(),
        ];

        app(UpdateHourlyStaffAction::class)->run([$record]);

        // Return updated record with includes
        $hourlyRecord = HourlyRecord::with('issues')->findOrFail($request->id);

        return Response::create($hourlyRecord, HourlyRecordTransformer::class)->ok();
    }
}
