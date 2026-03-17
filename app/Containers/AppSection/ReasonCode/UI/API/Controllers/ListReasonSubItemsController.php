<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\ReasonCode\Actions\ListReasonSubItemsAction;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ListReasonSubItemsRequest;
use App\Containers\AppSection\ReasonCode\UI\API\Transformers\ReasonSubItemTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListReasonSubItemsController extends ApiController
{
    public function __invoke(ListReasonSubItemsRequest $request): JsonResponse
    {
        $subItems = app(ListReasonSubItemsAction::class)->run($request);

        return Response::create($subItems, ReasonSubItemTransformer::class)->ok();
    }
}
