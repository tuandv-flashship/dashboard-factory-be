<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\GetAllProductionLinesAction;
use App\Containers\AppSection\Production\UI\API\Transformers\ProductionLineTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetAllProductionLinesController extends ApiController
{
    public function __construct(
        private readonly GetAllProductionLinesAction $action,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $lines = $this->action->run();

        return Response::create($lines, ProductionLineTransformer::class)->ok();
    }
}
