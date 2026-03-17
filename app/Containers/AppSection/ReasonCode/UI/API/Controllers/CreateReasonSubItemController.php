<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\CreateReasonSubItemAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\CreateReasonSubItemRequest;
use App\Containers\AppSection\ReasonCode\UI\API\Transformers\ReasonSubItemTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateReasonSubItemController extends ApiController
{
    public function __invoke(CreateReasonSubItemRequest $request): JsonResponse
    {
        $subItem = app(CreateReasonSubItemAction::class)->run($request);

        return Response::create($subItem, ReasonSubItemTransformer::class)->created();
    }
}
