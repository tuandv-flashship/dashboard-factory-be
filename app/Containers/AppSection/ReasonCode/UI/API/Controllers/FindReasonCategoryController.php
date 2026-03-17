<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\FindReasonCategoryAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\FindReasonCategoryRequest;
use App\Containers\AppSection\ReasonCode\UI\API\Transformers\ReasonCategoryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class FindReasonCategoryController extends ApiController
{
    public function __invoke(FindReasonCategoryRequest $request): JsonResponse
    {
        $category = app(FindReasonCategoryAction::class)->run($request);

        return Response::create($category, ReasonCategoryTransformer::class)->ok();
    }
}
