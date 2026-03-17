<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\UpdateReasonCategoryAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\UpdateReasonCategoryRequest;
use App\Containers\AppSection\ReasonCode\UI\API\Transformers\ReasonCategoryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateReasonCategoryController extends ApiController
{
    public function __invoke(UpdateReasonCategoryRequest $request): JsonResponse
    {
        $category = app(UpdateReasonCategoryAction::class)->run($request);

        return Response::create($category, ReasonCategoryTransformer::class)->ok();
    }
}
