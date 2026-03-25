<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Actions\CopyShiftTemplateAction;
use App\Containers\AppSection\Shift\UI\API\Requests\CopyShiftTemplateRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTemplateTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CopyShiftTemplateController extends ApiController
{
    public function __invoke(CopyShiftTemplateRequest $request): JsonResponse
    {
        $template = app(CopyShiftTemplateAction::class)->run($request);

        return Response::create($template, ShiftTemplateTransformer::class)->created();
    }
}
