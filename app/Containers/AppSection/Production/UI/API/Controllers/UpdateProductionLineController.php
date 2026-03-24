<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\UpdateProductionLineAction;
use App\Containers\AppSection\Production\UI\API\Requests\UpdateProductionLineRequest;
use App\Containers\AppSection\Production\UI\API\Transformers\ProductionLineTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateProductionLineController extends ApiController
{
    public function __invoke(UpdateProductionLineRequest $request): JsonResponse
    {
        $line = app(UpdateProductionLineAction::class)->run($request);

        return Response::create($line, ProductionLineTransformer::class)->ok();
    }
}
