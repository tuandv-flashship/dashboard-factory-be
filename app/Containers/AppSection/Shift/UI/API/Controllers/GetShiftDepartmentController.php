<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftDetailTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * Get shift detail for a specific department within a shift.
 *
 * GET /v1/admin/shifts/{id}/departments/{department_id}
 */
final class GetShiftDepartmentController extends ApiController
{
    public function __invoke(int $id, int $department_id): JsonResponse
    {
        $detail = ShiftDetail::with(['department.productionLine', 'machines.machine'])
            ->where('shift_id', $id)
            ->where('department_id', $department_id)
            ->firstOrFail();

        return Response::create($detail, ShiftDetailTransformer::class)->ok();
    }
}
