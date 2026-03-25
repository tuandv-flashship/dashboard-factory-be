<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Actions\CreateShiftTemplateAction;
use App\Containers\AppSection\Shift\UI\API\Requests\CreateShiftTemplateRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTemplateTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateShiftTemplateController extends ApiController
{
    public function __invoke(CreateShiftTemplateRequest $request): JsonResponse
    {
        $template = app(CreateShiftTemplateAction::class)->run($request);

        return Response::create($template, ShiftTemplateTransformer::class)->created();
    }
}
