<?php

namespace App\Containers\AppSection\Department\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Department\Actions\UpdateDepartmentAction;
use App\Containers\AppSection\Department\UI\API\Requests\UpdateDepartmentRequest;
use App\Containers\AppSection\Department\UI\API\Transformers\DepartmentTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateDepartmentController extends ApiController
{
    public function __invoke(UpdateDepartmentRequest $request): JsonResponse
    {
        $dept = app(UpdateDepartmentAction::class)->run($request);

        return Response::create($dept, DepartmentTransformer::class)->ok();
    }
}
