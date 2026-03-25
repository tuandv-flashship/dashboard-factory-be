<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\KpiRatingLevel\Actions\CreateKpiRatingLevelAction;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Requests\CreateKpiRatingLevelRequest;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Transformers\KpiRatingLevelTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateKpiRatingLevelController extends ApiController
{
    public function __invoke(CreateKpiRatingLevelRequest $request): JsonResponse
    {
        $ratingLevel = app(CreateKpiRatingLevelAction::class)->run($request);

        return Response::create($ratingLevel, KpiRatingLevelTransformer::class)->created();
    }
}
