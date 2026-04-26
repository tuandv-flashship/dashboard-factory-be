<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordTransformer;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\UI\API\Requests\CreateHourlyRecordRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class CreateHourlyRecordController extends ApiController
{
    public function __invoke(CreateHourlyRecordRequest $request): JsonResponse
    {
        $shiftId = $request->shift_id;
        $deptId  = $request->department_id;

        $record = DB::transaction(function () use ($shiftId, $deptId, $request) {
            // 1. Find the last hourly record for this department
            $lastRecord = HourlyRecord::withTrashed()
                ->where('shift_id', $shiftId)
                ->where('department_id', $deptId)
                ->orderByDesc('hour_index')
                ->first();

            $newHourIndex = $lastRecord ? $lastRecord->hour_index + 1 : 0;

            // 2. Compute hour_slot label from the last slot's end hour
            $hourSlot = $this->computeNextHourSlot($lastRecord, $newHourIndex);

            // 3. Create the new hourly record
            $kpiMinutes = (int) $request->input('kpi_minutes');

            $record = HourlyRecord::create([
                'shift_id'             => $shiftId,
                'department_id'        => $deptId,
                'hour_slot'            => $hourSlot,
                'hour_index'           => $newHourIndex,
                'kpi_minutes'          => $kpiMinutes,
                'kpi_hours'            => round($kpiMinutes / 60, 2),
                'kpi_percent'          => round($kpiMinutes / 60 * 100, 2),
                'target'               => $request->input('target'),
                'staff_required'       => $request->input('staff_required'),
                'note'                 => $request->input('note'),
                'staff'                => null,
                'actual'               => null,
                'hour_start_inventory' => 0,
                'efficiency'           => 0,
                'error_rate'           => 0,
                'status'               => HourlyRecordStatus::Pending->value,
            ]);

            // 4. Increase shift_details.work_hours by 1
            ShiftDetail::where('shift_id', $shiftId)
                ->where('department_id', $deptId)
                ->increment('work_hours', 1);

            return $record;
        });

        $record->load('issues');

        return Response::create($record, HourlyRecordTransformer::class)->ok();
    }

    /**
     * Compute the next hour slot label based on the last record.
     *
     * e.g., last slot "14h-15h" → next "15h-16h"
     */
    private function computeNextHourSlot(?HourlyRecord $lastRecord, int $newIndex): string
    {
        if (!$lastRecord) {
            return '0h-1h';
        }

        // Parse end hour from last slot (e.g., "14h-15h" → 15)
        $parts   = explode('-', $lastRecord->hour_slot);
        $endHour = (int) str_replace('h', '', $parts[1] ?? $parts[0]);

        $nextEnd = $endHour + 1;

        return "{$endHour}h-{$nextEnd}h";
    }
}
