<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Actions\UpdateShiftTemplateAction;
use App\Containers\AppSection\Shift\UI\API\Requests\UpdateShiftTemplateRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTemplateTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateShiftTemplateController extends ApiController
{
    public function __invoke(UpdateShiftTemplateRequest $request): JsonResponse
    {
        $template = app(UpdateShiftTemplateAction::class)->run($request);

        return Response::create($template, ShiftTemplateTransformer::class)->ok();
    }
}
