<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Actions\ListShiftTemplatesAction;
use App\Containers\AppSection\Shift\UI\API\Requests\ListShiftTemplatesRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTemplateTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListShiftTemplatesController extends ApiController
{
    public function __invoke(ListShiftTemplatesRequest $request): JsonResponse
    {
        $templates = app(ListShiftTemplatesAction::class)->run($request);

        return Response::create($templates, ShiftTemplateTransformer::class)->ok();
    }
}
