<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Actions\FindShiftWithDetailsAction;
use App\Containers\AppSection\Shift\UI\API\Requests\FindShiftRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class FindShiftController extends ApiController
{
    public function __invoke(FindShiftRequest $request): JsonResponse
    {
        $shift = app(FindShiftWithDetailsAction::class)->run((int) $request->id);

        return Response::create($shift, ShiftTransformer::class)->ok();
    }
}
