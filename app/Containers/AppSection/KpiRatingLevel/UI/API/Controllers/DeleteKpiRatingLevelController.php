<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\KpiRatingLevel\Actions\DeleteKpiRatingLevelAction;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Requests\DeleteKpiRatingLevelRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteKpiRatingLevelController extends ApiController
{
    public function __invoke(DeleteKpiRatingLevelRequest $request): JsonResponse
    {
        app(DeleteKpiRatingLevelAction::class)->run($request);

        return Response::create()->noContent();
    }
}
