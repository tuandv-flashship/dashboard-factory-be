<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\KpiRatingLevel\Actions\FindKpiRatingLevelAction;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Requests\FindKpiRatingLevelRequest;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Transformers\KpiRatingLevelTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class FindKpiRatingLevelController extends ApiController
{
    public function __invoke(FindKpiRatingLevelRequest $request): JsonResponse
    {
        $ratingLevel = app(FindKpiRatingLevelAction::class)->run($request);

        return Response::create($ratingLevel, KpiRatingLevelTransformer::class)->ok();
    }
}
