<?php

namespace App\Containers\AppSection\Department\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Department\Actions\DeleteDepartmentAction;
use App\Containers\AppSection\Department\UI\API\Requests\DeleteDepartmentRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class DeleteDepartmentController extends ApiController
{
    public function __invoke(DeleteDepartmentRequest $request): JsonResponse
    {
        app(DeleteDepartmentAction::class)->run($request);

        return Response::create()->noContent();
    }
}
