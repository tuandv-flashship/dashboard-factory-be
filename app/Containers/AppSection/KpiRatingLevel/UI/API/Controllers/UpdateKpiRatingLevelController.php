<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\KpiRatingLevel\Actions\UpdateKpiRatingLevelAction;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Requests\UpdateKpiRatingLevelRequest;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Transformers\KpiRatingLevelTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateKpiRatingLevelController extends ApiController
{
    public function __invoke(UpdateKpiRatingLevelRequest $request): JsonResponse
    {
        $ratingLevel = app(UpdateKpiRatingLevelAction::class)->run($request);

        return Response::create($ratingLevel, KpiRatingLevelTransformer::class)->ok();
    }
}
