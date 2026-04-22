<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Production\Actions\UpdateProductionSchedulerSettingsAction;
use App\Containers\AppSection\Production\UI\API\Requests\UpdateProductionSchedulerSettingsRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateProductionSchedulerSettingsController extends ApiController
{
    public function __invoke(UpdateProductionSchedulerSettingsRequest $request): JsonResponse
    {
        app(UpdateProductionSchedulerSettingsAction::class)->run($request->validated());

        return response()->json(['message' => 'Scheduler settings updated.']);
    }
}
