<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Support\DepartmentSummary;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftDetailTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * Get shift detail + production summary for a specific department within a shift.
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

        $shift = Shift::findOrFail($id);

        // Load hourly records for this department within this shift
        $records = HourlyRecord::where('shift_id', $id)
            ->where('department_id', $department_id)
            ->orderBy('hour_index')
            ->get();

        $shiftDate = CarbonImmutable::parse($shift->date);

        // Reuse DepartmentSummary from Production container
        $summary = DepartmentSummary::build($records, $detail->department, $detail, $shiftDate);

        $detailData = (new ShiftDetailTransformer())->transform($detail);

        return response()->json([
            'data' => array_merge($detailData, [
                'summary' => $summary,
            ]),
        ]);
    }
}
