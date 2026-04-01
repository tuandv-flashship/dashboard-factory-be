<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Actions\UpdateShiftAction;
use App\Containers\AppSection\Shift\UI\API\Requests\UpdateShiftRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateShiftController extends ApiController
{
    public function __invoke(UpdateShiftRequest $request): JsonResponse
    {
        $shift = app(UpdateShiftAction::class)->run($request->id, $request->validated());

        return Response::create($shift, ShiftTransformer::class)->ok();
    }
}
