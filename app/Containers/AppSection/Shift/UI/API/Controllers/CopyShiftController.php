<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Shift\Actions\CopyShiftAction;
use App\Containers\AppSection\Shift\UI\API\Requests\CopyShiftRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CopyShiftController extends ApiController
{
    public function __invoke(CopyShiftRequest $request): JsonResponse
    {
        $shifts = app(CopyShiftAction::class)->run($request->id, $request->input('target_dates'));

        $transformed = array_map(
            fn ($shift) => $this->transform($shift, ShiftTransformer::class),
            $shifts
        );

        return $this->created(['data' => $transformed]);
    }
}
