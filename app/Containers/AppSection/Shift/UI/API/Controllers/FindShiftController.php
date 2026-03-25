<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Shift\Actions\FindShiftWithDetailsAction;
use App\Containers\AppSection\Shift\UI\API\Requests\FindShiftRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;

final class FindShiftController extends ApiController
{
    public function __invoke(FindShiftRequest $request): array
    {
        $shift = app(FindShiftWithDetailsAction::class)->run($request->id);

        return $this->transform($shift, ShiftTransformer::class, includes: ['hourlyRecords']);
    }
}
