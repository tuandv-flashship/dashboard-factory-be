<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Shift\Actions\UpdateShiftAction;
use App\Containers\AppSection\Shift\UI\API\Requests\UpdateShiftRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;

final class UpdateShiftController extends ApiController
{
    public function __invoke(UpdateShiftRequest $request): array
    {
        $shift = app(UpdateShiftAction::class)->run($request->id, $request->validated());

        return $this->transform($shift, ShiftTransformer::class);
    }
}
