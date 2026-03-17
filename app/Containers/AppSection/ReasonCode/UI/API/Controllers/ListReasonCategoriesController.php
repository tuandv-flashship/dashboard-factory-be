<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\ListReasonCategoriesAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ListReasonCategoriesRequest;
use App\Containers\AppSection\ReasonCode\UI\API\Transformers\ReasonCategoryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListReasonCategoriesController extends ApiController
{
    public function __invoke(ListReasonCategoriesRequest $request): JsonResponse
    {
        $categories = app(ListReasonCategoriesAction::class)->run($request);

        return Response::create($categories, ReasonCategoryTransformer::class)->ok();
    }
}
