<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\DeleteReasonCategoryAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\DeleteReasonCategoryRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteReasonCategoryController extends ApiController
{
    public function __invoke(DeleteReasonCategoryRequest $request): JsonResponse
    {
        app(DeleteReasonCategoryAction::class)->run($request);

        return Response::create()->noContent();
    }
}
