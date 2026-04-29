<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Actions\UpdateHourlyStaffAction;
use App\Containers\AppSection\Shift\Actions\FindShiftWithDetailsAction;
use App\Containers\AppSection\Shift\Traits\InvalidatesProductionCache;
use App\Containers\AppSection\Shift\UI\API\Requests\UpdateHourlyStaffRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;

final class UpdateHourlyStaffController extends ApiController
{
    use InvalidatesProductionCache;

    public function __invoke(UpdateHourlyStaffRequest $request): array
    {
        app(UpdateHourlyStaffAction::class)->run($request->input('records'));

        // Invalidate cache for all affected departments
        $recordIds = collect($request->input('records'))->pluck('id')->filter();
        $affected  = HourlyRecord::whereIn('id', $recordIds)
            ->select('shift_id', 'department_id')
            ->distinct()
            ->get();

        foreach ($affected as $row) {
            $this->invalidateProductionCache($row->shift_id, $row->department_id);
        }

        // Return updated shift with hourly records
        $shift = app(FindShiftWithDetailsAction::class)->run($request->id);

        return $this->transform($shift, ShiftTransformer::class, includes: ['hourlyRecords']);
    }
}
