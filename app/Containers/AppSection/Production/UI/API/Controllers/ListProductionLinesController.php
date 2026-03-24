<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\ListAllProductionLinesAction;
use App\Containers\AppSection\Production\UI\API\Requests\ListProductionLinesRequest;
use App\Containers\AppSection\Production\UI\API\Transformers\ProductionLineTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListProductionLinesController extends ApiController
{
    public function __invoke(ListProductionLinesRequest $request): JsonResponse
    {
        $lines = app(ListAllProductionLinesAction::class)->run($request);

        return Response::create($lines, ProductionLineTransformer::class)->ok();
    }
}
