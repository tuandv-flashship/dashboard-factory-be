<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Actions\CreateShiftAction;
use App\Containers\AppSection\Shift\UI\API\Requests\CreateShiftRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateShiftController extends ApiController
{
    public function __invoke(CreateShiftRequest $request): JsonResponse
    {
        $shift = app(CreateShiftAction::class)->run($request->validated());

        return Response::create($shift, ShiftTransformer::class)->created();
    }
}
