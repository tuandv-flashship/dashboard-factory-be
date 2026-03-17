<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\UpdateReasonSubItemAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\UpdateReasonSubItemRequest;
use App\Containers\AppSection\ReasonCode\UI\API\Transformers\ReasonSubItemTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateReasonSubItemController extends ApiController
{
    public function __invoke(UpdateReasonSubItemRequest $request): JsonResponse
    {
        $subItem = app(UpdateReasonSubItemAction::class)->run($request);

        return Response::create($subItem, ReasonSubItemTransformer::class)->ok();
    }
}
