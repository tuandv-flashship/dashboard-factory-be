<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Actions\FindDepartmentAction;
use App\Containers\AppSection\Production\UI\API\Requests\FindDepartmentRequest;
use App\Containers\AppSection\Production\UI\API\Transformers\DepartmentTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class FindDepartmentController extends ApiController
{
    public function __invoke(FindDepartmentRequest $request): JsonResponse
    {
        $dept = app(FindDepartmentAction::class)->run($request);

        return Response::create($dept, DepartmentTransformer::class)->ok();
    }
}
