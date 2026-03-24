<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\CreateProductionLineAction;
use App\Containers\AppSection\Production\UI\API\Requests\CreateProductionLineRequest;
use App\Containers\AppSection\Production\UI\API\Transformers\ProductionLineTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateProductionLineController extends ApiController
{
    public function __invoke(CreateProductionLineRequest $request): JsonResponse
    {
        $line = app(CreateProductionLineAction::class)->run($request);

        return Response::create($line, ProductionLineTransformer::class)->created();
    }
}
