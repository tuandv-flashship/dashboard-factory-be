<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\KpiRatingLevel\Actions\ListKpiRatingLevelsAction;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Requests\ListKpiRatingLevelsRequest;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Transformers\KpiRatingLevelTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListKpiRatingLevelsController extends ApiController
{
    public function __invoke(ListKpiRatingLevelsRequest $request): JsonResponse
    {
        $ratingLevels = app(ListKpiRatingLevelsAction::class)->run($request);

        return Response::create($ratingLevels, KpiRatingLevelTransformer::class)->ok();
    }
}
