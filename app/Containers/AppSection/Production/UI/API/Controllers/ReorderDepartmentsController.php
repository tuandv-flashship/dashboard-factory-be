<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Production\Actions\ReorderDepartmentsAction;
use App\Containers\AppSection\Production\UI\API\Requests\ReorderDepartmentsRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ReorderDepartmentsController extends ApiController
{
    public function __invoke(ReorderDepartmentsRequest $request): JsonResponse
    {
        app(ReorderDepartmentsAction::class)->run($request);

        return response()->json(['message' => 'Reordered successfully']);
    }
}
