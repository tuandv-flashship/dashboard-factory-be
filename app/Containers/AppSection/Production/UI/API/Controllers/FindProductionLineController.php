<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\FindProductionLineAction;
use App\Containers\AppSection\Production\UI\API\Requests\FindProductionLineRequest;
use App\Containers\AppSection\Production\UI\API\Transformers\ProductionLineTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class FindProductionLineController extends ApiController
{
    public function __invoke(FindProductionLineRequest $request): JsonResponse
    {
        $line = app(FindProductionLineAction::class)->run($request);

        return Response::create($line, ProductionLineTransformer::class)->ok();
    }
}
