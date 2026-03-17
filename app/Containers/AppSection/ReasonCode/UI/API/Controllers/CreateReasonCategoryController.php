<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\CreateReasonCategoryAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\CreateReasonCategoryRequest;
use App\Containers\AppSection\ReasonCode\UI\API\Transformers\ReasonCategoryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateReasonCategoryController extends ApiController
{
    public function __invoke(CreateReasonCategoryRequest $request): JsonResponse
    {
        $category = app(CreateReasonCategoryAction::class)->run($request);

        return Response::create($category, ReasonCategoryTransformer::class)->created();
    }
}
