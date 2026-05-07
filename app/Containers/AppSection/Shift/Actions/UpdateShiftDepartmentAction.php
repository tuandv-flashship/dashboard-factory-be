<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Production\Tasks\SyncHourlyRecordsTask as ProductionSyncTask;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Tasks\SyncHourlyRecordsTask;
use App\Containers\AppSection\Shift\Tasks\SyncShiftDetailMachinesTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class UpdateShiftDepartmentAction extends ParentAction
{
    public function __construct(
        private readonly SyncShiftDetailMachinesTask $syncMachinesTask,
        private readonly SyncHourlyRecordsTask $syncHourlyRecordsTask,
        private readonly ProductionSyncTask $productionSyncTask,
    ) {
    }

    public function run(int $shiftId, int $departmentId, array $data): Shift
    {
        $changedDetailId = null;

        $shift = DB::transaction(function () use ($shiftId, $departmentId, $data, &$changedDetailId) {
            $shift = Shift::findOrFail($shiftId);

            // The payload must contain shift_number to properly identify the ShiftDetail
            $shiftNumber = $data['shift_number'] ?? $shift->shift_number;

            $shiftDetail = ShiftDetail::where('shift_id', $shift->id)
                ->where('department_id', $departmentId)
                ->where('shift_number', $shiftNumber)
                ->first();

            $beforeWorkHours = $shiftDetail ? (float) $shiftDetail->work_hours : null;

            $dbColumns = [
                'headcount', 'machine_count',
                'start_time', 'work_hours', 'prep_minutes',
                'break1_start', 'break1_minutes', 'meal_break_start', 'meal_break_minutes',
                'break2_start', 'break2_minutes', 'break3_start', 'break3_minutes',
            ];

            $updateData = collect($data)->only($dbColumns)->toArray();

            if ($shiftDetail) {
                // Partial update: only update provided fields
                if (!empty($updateData)) {
                    $shiftDetail->update($updateData);
                }
            } else {
                abort(404, "ShiftDetail not found for shift #{$shift->id}, department #{$departmentId}, shift_number #{$shiftNumber}. Ensure the shift was created properly.");
            }

            // Sync per_machine pivot if machine_ids is present
            if (isset($data['machine_ids'])) {
                // Format the payload to match what the task expects
                $machinePayload = [
                    [
                        'department_id' => $departmentId,
                        'shift_number'  => $shiftNumber,
                        'machine_ids'   => $data['machine_ids'],
                    ]
                ];
                $this->syncMachinesTask->run($shift, $machinePayload);
            }

            // Smart sync hourly records specifically for this department
            $this->syncHourlyRecordsTask->run($shift, $departmentId);

            // Detect if work_hours changed
            $newWorkHours = isset($data['work_hours']) ? (float) $data['work_hours'] : ($shiftDetail ? (float) $shiftDetail->work_hours : null);
            if ($beforeWorkHours !== null && $newWorkHours !== null && abs($beforeWorkHours - $newWorkHours) > 0.001) {
                $changedDetailId = $shiftDetail->id;
            }

            return $shift->load(['details.department.productionLine', 'details.machines.machine', 'template', 'hourlyRecords']);
        });

        // ── Dispatch FPlatform resync AFTER transaction commits ──
        if ($changedDetailId) {
            $this->resyncChangedDepartment($shift, $changedDetailId, $departmentId);
        }

        // ── Invalidate production dashboard caches optimally ──
        ProductionCacheKeys::flushForDepartment($shift, $departmentId);

        return $shift;
    }

    /**
     * Dispatch FPlatform resync for the affected ShiftDetail (after transaction commits).
     */
    private function resyncChangedDepartment(Shift $shift, int $changedDetailId, int $departmentId): void
    {
        $shiftDate = $shift->date->toDateString();
        $shiftNum  = $shift->shift_number;

        Log::info('[UpdateShiftDepartment] work_hours changed — auto-dispatching FPlatform resync.', [
            'shift_id'      => $shift->id,
            'date'          => $shiftDate,
            'shift'         => $shiftNum,
            'department_id' => $departmentId,
            'detail_id'     => $changedDetailId,
        ]);

        $this->productionSyncTask->run(
            date:          $shiftDate,
            shiftNumber:   $shiftNum,
            shiftDetailId: $changedDetailId,
        );
    }
}
