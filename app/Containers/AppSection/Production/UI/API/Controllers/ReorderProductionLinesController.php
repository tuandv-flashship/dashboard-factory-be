<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Production\Actions\ReorderProductionLinesAction;
use App\Containers\AppSection\Production\UI\API\Requests\ReorderProductionLinesRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ReorderProductionLinesController extends ApiController
{
    public function __invoke(ReorderProductionLinesRequest $request): JsonResponse
    {
        app(ReorderProductionLinesAction::class)->run($request);

        return response()->json(['message' => 'Reordered successfully']);
    }
}
