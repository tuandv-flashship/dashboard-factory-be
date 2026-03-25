<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Shift\Actions\UpdateHourlyStaffAction;
use App\Containers\AppSection\Shift\Actions\FindShiftWithDetailsAction;
use App\Containers\AppSection\Shift\UI\API\Requests\UpdateHourlyStaffRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;

final class UpdateHourlyStaffController extends ApiController
{
    public function __invoke(UpdateHourlyStaffRequest $request): array
    {
        app(UpdateHourlyStaffAction::class)->run($request->input('records'));

        // Return updated shift with hourly records
        $shift = app(FindShiftWithDetailsAction::class)->run($request->id);

        return $this->transform($shift, ShiftTransformer::class, includes: ['hourlyRecords']);
    }
}
