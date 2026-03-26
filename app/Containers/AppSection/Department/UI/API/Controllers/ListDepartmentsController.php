<?php

namespace App\Containers\AppSection\Department\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Department\Actions\ListAllDepartmentsAction;
use App\Containers\AppSection\Department\UI\API\Requests\ListDepartmentsRequest;
use App\Containers\AppSection\Department\UI\API\Transformers\DepartmentTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListDepartmentsController extends ApiController
{
    public function __invoke(ListDepartmentsRequest $request): JsonResponse
    {
        $departments = app(ListAllDepartmentsAction::class)->run($request);

        return Response::create($departments, DepartmentTransformer::class)->ok();
    }
}
