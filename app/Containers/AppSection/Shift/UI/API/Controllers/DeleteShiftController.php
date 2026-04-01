<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Actions\DeleteShiftAction;
use App\Containers\AppSection\Shift\UI\API\Requests\DeleteShiftRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteShiftController extends ApiController
{
    public function __invoke(DeleteShiftRequest $request): JsonResponse
    {
        app(DeleteShiftAction::class)->run($request->id);

        return Response::create()->noContent();
    }
}
