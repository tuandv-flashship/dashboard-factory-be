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
        // Parse is_active: null means "show all", true/false means filtered
        $isActive = $request->has('is_active')
            ? filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : true; // default: active only

        $categories = $this->action->run(
            line:         $request->query('line'),
            dept:         $request->query('dept'),
            scopeType:    $request->query('scope_type'),
            isActive:     $isActive,
            search:       $request->query('search'),
            categoryCode: $request->query('category_code'),
        );

        return Response::create($categories, ReasonCategoryTransformer::class)
            ->parseIncludes($request->query('include') ?? $request->query('Include') ?? '')
            ->ok();
    }
}
