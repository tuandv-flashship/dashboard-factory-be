<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\DeleteReasonErrorAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\DeleteReasonErrorRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteReasonErrorController extends ApiController
{
    public function __invoke(DeleteReasonErrorRequest $request): JsonResponse
    {
        app(DeleteReasonErrorAction::class)->run($request);

        return Response::create()->noContent();
    }
}
