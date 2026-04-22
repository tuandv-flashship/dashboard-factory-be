<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Production\Actions\GetProductionSchedulerSettingsAction;
use App\Containers\AppSection\Production\UI\API\Requests\GetProductionSchedulerSettingsRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetProductionSchedulerSettingsController extends ApiController
{
    public function __invoke(GetProductionSchedulerSettingsRequest $request): JsonResponse
    {
        $settings = app(GetProductionSchedulerSettingsAction::class)->run();

        return response()->json($settings);
    }
}
