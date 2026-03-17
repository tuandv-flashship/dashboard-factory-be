<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\GetReasonCodesAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\GetReasonCodesRequest;
use App\Containers\AppSection\ReasonCode\UI\API\Transformers\ReasonCategoryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetReasonCodesController extends ApiController
{
    public function __construct(
        private readonly GetReasonCodesAction $action,
    ) {
    }

    public function __invoke(GetReasonCodesRequest $request): JsonResponse
    {
        $categories = $this->action->run(
            line: $request->query('line'),
            dept: $request->query('dept'),
        );

        return Response::create($categories, ReasonCategoryTransformer::class)
            ->parseIncludes($request->query('include') ?? $request->query('Include') ?? '')
            ->ok();
    }
}
