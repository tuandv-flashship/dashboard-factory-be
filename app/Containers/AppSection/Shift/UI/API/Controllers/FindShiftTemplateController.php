<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Actions\FindShiftTemplateAction;
use App\Containers\AppSection\Shift\UI\API\Requests\FindShiftTemplateRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTemplateTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class FindShiftTemplateController extends ApiController
{
    public function __invoke(FindShiftTemplateRequest $request): JsonResponse
    {
        $template = app(FindShiftTemplateAction::class)->run($request);

        return Response::create($template, ShiftTemplateTransformer::class)
            ->parseIncludes('details')
            ->addMeta([
                'supervisors' => config('appSection-shift.supervisors', []),
            ])
            ->ok();
    }
}
