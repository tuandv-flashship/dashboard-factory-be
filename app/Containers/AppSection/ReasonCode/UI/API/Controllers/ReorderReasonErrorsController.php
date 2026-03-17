<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use App\Containers\AppSection\ReasonCode\Actions\ReorderReasonErrorsAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ReorderReasonErrorsRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ReorderReasonErrorsController extends ApiController
{
    public function __invoke(ReorderReasonErrorsRequest $request): JsonResponse
    {
        app(ReorderReasonErrorsAction::class)->run($request);

        return response()->json(['message' => 'Reordered successfully.']);
    }
}
