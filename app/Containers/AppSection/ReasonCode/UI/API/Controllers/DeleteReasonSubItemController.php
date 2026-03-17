<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\DeleteReasonSubItemAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\DeleteReasonSubItemRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteReasonSubItemController extends ApiController
{
    public function __invoke(DeleteReasonSubItemRequest $request): JsonResponse
    {
        app(DeleteReasonSubItemAction::class)->run($request);

        return Response::create()->noContent();
    }
}
