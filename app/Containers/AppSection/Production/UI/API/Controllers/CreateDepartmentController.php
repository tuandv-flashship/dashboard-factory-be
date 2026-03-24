<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\CreateDepartmentAction;
use App\Containers\AppSection\Production\UI\API\Requests\CreateDepartmentRequest;
use App\Containers\AppSection\Production\UI\API\Transformers\DepartmentTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateDepartmentController extends ApiController
{
    public function __invoke(CreateDepartmentRequest $request): JsonResponse
    {
        $dept = app(CreateDepartmentAction::class)->run($request);

        return Response::create($dept, DepartmentTransformer::class)->created();
    }
}
