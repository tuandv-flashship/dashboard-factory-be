<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\UpdateReasonErrorAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\UpdateReasonErrorRequest;
use App\Containers\AppSection\ReasonCode\UI\API\Transformers\ReasonErrorTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateReasonErrorController extends ApiController
{
    public function __invoke(UpdateReasonErrorRequest $request): JsonResponse
    {
        $error = app(UpdateReasonErrorAction::class)->run($request);

        return Response::create($error, ReasonErrorTransformer::class)->ok();
    }
}
