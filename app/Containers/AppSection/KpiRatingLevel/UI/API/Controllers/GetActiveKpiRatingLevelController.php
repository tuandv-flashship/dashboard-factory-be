<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers;

use App\Containers\AppSection\KpiRatingLevel\Actions\GetActiveKpiRatingLevelAction;
use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Requests\GetActiveKpiRatingLevelRequest;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Transformers\KpiRatingLevelTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Apiato\Support\Facades\Response;
use Illuminate\Http\JsonResponse;

final class GetActiveKpiRatingLevelController extends ApiController
{
    public function __invoke(GetActiveKpiRatingLevelRequest $request): JsonResponse
    {
        $result = app(GetActiveKpiRatingLevelAction::class)->run();

        // If DB record found, use transformer
        if ($result instanceof KpiRatingLevel) {
            return Response::create($result, KpiRatingLevelTransformer::class)->ok();
        }

        // Fallback: return config default as-is
        return response()->json(['data' => $result]);
    }
}
