<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\DeleteProductionLineAction;
use App\Containers\AppSection\Production\UI\API\Requests\DeleteProductionLineRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteProductionLineController extends ApiController
{
    public function __invoke(DeleteProductionLineRequest $request): JsonResponse
    {
        app(DeleteProductionLineAction::class)->run($request);

        return Response::create()->noContent();
    }
}
