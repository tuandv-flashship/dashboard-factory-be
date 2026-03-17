<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\ListReasonErrorsAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ListReasonErrorsRequest;
use App\Containers\AppSection\ReasonCode\UI\API\Transformers\ReasonErrorTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListReasonErrorsController extends ApiController
{
    public function __invoke(ListReasonErrorsRequest $request): JsonResponse
    {
        $errors = app(ListReasonErrorsAction::class)->run($request);

        return Response::create($errors, ReasonErrorTransformer::class)->ok();
    }
}
