<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\CreateReasonErrorAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\CreateReasonErrorRequest;
use App\Containers\AppSection\ReasonCode\UI\API\Transformers\ReasonErrorTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateReasonErrorController extends ApiController
{
    public function __invoke(CreateReasonErrorRequest $request): JsonResponse
    {
        $error = app(CreateReasonErrorAction::class)->run($request);

        return Response::create($error, ReasonErrorTransformer::class)->created();
    }
}
