<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Shift\Actions\ReorderShiftTemplatesAction;
use App\Containers\AppSection\Shift\UI\API\Requests\ReorderShiftTemplatesRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ReorderShiftTemplatesController extends ApiController
{
    public function __invoke(ReorderShiftTemplatesRequest $request): JsonResponse
    {
        app(ReorderShiftTemplatesAction::class)->run($request);

        return response()->json(['message' => 'Reordered successfully']);
    }
}
