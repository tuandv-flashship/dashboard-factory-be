<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Traits\InvalidatesProductionCache;
use App\Containers\AppSection\Shift\UI\API\Requests\DeleteLastHourlyRecordRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class DeleteLastHourlyRecordController extends ApiController
{
    use InvalidatesProductionCache;
    public function __invoke(DeleteLastHourlyRecordRequest $request): JsonResponse
    {
        $shiftId = $request->shift_id;
        $deptId  = $request->department_id;

        DB::transaction(function () use ($shiftId, $deptId) {
            $totalRecords = HourlyRecord::where('shift_id', $shiftId)
                ->where('department_id', $deptId)
                ->count();

            if ($totalRecords <= 1) {
                throw new UnprocessableEntityHttpException(
                    'Không thể xóa khung giờ cuối cùng. Bộ phận phải có ít nhất 1 khung giờ.'
                );
            }

            // Find and soft-delete the last record
            $lastRecord = HourlyRecord::where('shift_id', $shiftId)
                ->where('department_id', $deptId)
                ->orderByDesc('hour_index')
                ->firstOrFail();

            $lastRecord->delete();

            // Decrease shift_details.work_hours by 1 (min 0)
            $detail = ShiftDetail::where('shift_id', $shiftId)
                ->where('department_id', $deptId)
                ->first();

            if ($detail) {
                $detail->update([
                    'work_hours' => max(0, $detail->work_hours - 1),
                ]);
            }
        });

        // Invalidate production dashboard cache for historical shifts
        $this->invalidateProductionCache($shiftId, $deptId);

        return response()->json(null, 204);
    }
}
