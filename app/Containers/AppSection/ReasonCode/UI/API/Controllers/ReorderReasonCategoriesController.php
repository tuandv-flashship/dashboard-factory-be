<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use App\Containers\AppSection\ReasonCode\Actions\ReorderReasonCategoriesAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ReorderReasonCategoriesRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ReorderReasonCategoriesController extends ApiController
{
    public function __invoke(ReorderReasonCategoriesRequest $request): JsonResponse
    {
        app(ReorderReasonCategoriesAction::class)->run($request);

        return response()->json(['message' => 'Reordered successfully.']);
    }
}
