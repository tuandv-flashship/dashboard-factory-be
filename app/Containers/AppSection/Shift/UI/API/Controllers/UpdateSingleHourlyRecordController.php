<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordTransformer;
use App\Containers\AppSection\Shift\Actions\UpdateHourlyStaffAction;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Traits\InvalidatesProductionCache;
use App\Containers\AppSection\Shift\UI\API\Requests\UpdateSingleHourlyRecordRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateSingleHourlyRecordController extends ApiController
{
    use InvalidatesProductionCache;

    public function __invoke(UpdateSingleHourlyRecordRequest $request): JsonResponse
    {
        // Reuse batch Task — wrap single record in array format
        $record = [
            'id' => $request->id,
            ...$request->validated(),
        ];

        app(UpdateHourlyStaffAction::class)->run([$record]);

        // Return updated record with includes
        $hourlyRecord = HourlyRecord::with(['issues', 'department'])->findOrFail($request->id);

        // Manually load shiftDetail — compound key doesn't support eager loading
        $shiftDetail = ShiftDetail::where('shift_id', $hourlyRecord->shift_id)
            ->where('department_id', $hourlyRecord->department_id)
            ->first();

        if ($shiftDetail) {
            $hourlyRecord->setRelation('shiftDetail', $shiftDetail);
        }

        // Invalidate production dashboard cache for historical shifts
        $this->invalidateProductionCache($hourlyRecord->shift_id, $hourlyRecord->department_id);

        return Response::create($hourlyRecord, HourlyRecordTransformer::class)->ok();
    }
}
