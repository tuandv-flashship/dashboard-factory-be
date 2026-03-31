<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers;

use App\Containers\AppSection\KpiRatingLevel\Actions\GetDefaultKpiRatingLevelAction;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Requests\GetDefaultKpiRatingLevelRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetDefaultKpiRatingLevelController extends ApiController
{
    public function __invoke(GetDefaultKpiRatingLevelRequest $request): JsonResponse
    {
        $default = app(GetDefaultKpiRatingLevelAction::class)->run();

        return response()->json(['data' => $default]);
    }
}
