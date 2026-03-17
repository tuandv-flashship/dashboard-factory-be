<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use App\Containers\AppSection\ReasonCode\Actions\ReorderReasonSubItemsAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ReorderReasonSubItemsRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ReorderReasonSubItemsController extends ApiController
{
    public function __invoke(ReorderReasonSubItemsRequest $request): JsonResponse
    {
        app(ReorderReasonSubItemsAction::class)->run($request);

        return response()->json(['message' => 'Reordered successfully.']);
    }
}
