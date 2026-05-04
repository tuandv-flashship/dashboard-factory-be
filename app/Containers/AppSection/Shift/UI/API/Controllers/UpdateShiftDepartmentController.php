<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Actions\UpdateShiftDepartmentAction;
use App\Containers\AppSection\Shift\UI\API\Requests\UpdateShiftDepartmentRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateShiftDepartmentController extends ApiController
{
    public function __invoke(UpdateShiftDepartmentRequest $request, int $id, int $department_id): JsonResponse
    {
        $shift = app(UpdateShiftDepartmentAction::class)->run($id, $department_id, $request->validated());

        return Response::create($shift, ShiftTransformer::class)->ok();
    }
}
