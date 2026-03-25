<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Shift\Actions\DeleteShiftTemplateAction;
use App\Containers\AppSection\Shift\UI\API\Requests\DeleteShiftTemplateRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteShiftTemplateController extends ApiController
{
    public function __invoke(DeleteShiftTemplateRequest $request): JsonResponse
    {
        app(DeleteShiftTemplateAction::class)->run($request);

        return response()->json(null, 204);
    }
}
