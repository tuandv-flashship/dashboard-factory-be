<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Production\Tasks\SyncHourlyRecordsTask as ProductionSyncTask;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Tasks\SyncHourlyRecordsTask;
use App\Containers\AppSection\Shift\Tasks\SyncShiftDetailsTask;
use App\Containers\AppSection\Shift\Traits\RecalculatesShiftTimes;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class UpdateShiftAction extends ParentAction
{
    use RecalculatesShiftTimes;
    public function __construct(
        private readonly SyncShiftDetailsTask $syncShiftDetailsTask,
        private readonly SyncHourlyRecordsTask $syncHourlyRecordsTask,
        private readonly ProductionSyncTask $productionSyncTask,
    ) {
    }

    public function run(int $id, array $data): Shift
    {
        // ── Capture changed detail IDs BEFORE transaction alters DB ──
        $changedDetailIds = [];

        $shift = DB::transaction(function () use ($id, $data, &$changedDetailIds) {
            $shift = Shift::findOrFail($id);

            // Update header fields
            $headerFields = collect($data)->only([
                'supervisor', 'is_active',
            ])->toArray();

            if (!empty($headerFields)) {
                $shift->update($headerFields);
            }

            // Sync details if provided
            if (isset($data['details'])) {
                // Snapshot work_hours BEFORE upsert to detect changes
                $beforeSnapshot = $this->snapshotWorkHours($shift);

                $this->syncShiftDetailsTask->run($shift, $data['details']);

                // Smart sync hourly records: preserve actual data, soft-delete stale
                $this->syncHourlyRecordsTask->run($shift);

                // ── Recalculate Shift header end_time = max dept end_time ──
                $this->recalculateShiftEndTime($shift);

                // Detect which ShiftDetails had work_hours changed
                $changedDetailIds = $this->detectChangedDetails($shift, $beforeSnapshot, $data['details']);
            }

            return $shift->load(['details.department.productionLine', 'details.machines.machine', 'template', 'hourlyRecords']);
        });

        // ── Dispatch FPlatform resync AFTER transaction commits ──
        // (never inside transaction: job dispatch survives even if transaction rolls back)
        if (!empty($changedDetailIds)) {
            $this->resyncChangedDepartments($shift, $changedDetailIds);
        }

        // ── Invalidate production dashboard caches ──
        ProductionCacheKeys::flushForShift($shift);

        return $shift;
    }

    /**
     * Snapshot the current work_hours for each ShiftDetail before upsert.
     *
     * @return array<int, float>  department_id → work_hours
     */
    private function snapshotWorkHours(Shift $shift): array
    {
        return ShiftDetail::where('shift_id', $shift->id)
            ->get()
            ->pluck('work_hours', 'department_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }

    /**
     * Compare new work_hours payload vs snapshot and return changed ShiftDetail IDs.
     *
     * @return int[]
     */
    private function detectChangedDetails(Shift $shift, array $beforeSnapshot, array $detailsData): array
    {
        $payloadWorkHours = collect($detailsData)
            ->filter(fn ($d) => isset($d['department_id'], $d['work_hours']))
            ->pluck('work_hours', 'department_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $changedDeptIds = [];

        foreach ($payloadWorkHours as $deptId => $newWorkHours) {
            $oldWorkHours = $beforeSnapshot[(int) $deptId] ?? null;

            if ($oldWorkHours !== null && abs($oldWorkHours - $newWorkHours) > 0.001) {
                $changedDeptIds[] = (int) $deptId;
            }
        }

        if (empty($changedDeptIds)) {
            return [];
        }

        // Return ShiftDetail IDs (not dept IDs) for direct use in resync
        return ShiftDetail::where('shift_id', $shift->id)
            ->whereIn('department_id', $changedDeptIds)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Dispatch FPlatform resync for each affected ShiftDetail (after transaction commits).
     *
     * @param int[] $changedDetailIds
     */
    private function resyncChangedDepartments(Shift $shift, array $changedDetailIds): void
    {
        $shiftDate = $shift->date->toDateString();
        $shiftNum  = $shift->shift_number;

        $details = ShiftDetail::with('department')
            ->whereIn('id', $changedDetailIds)
            ->get();

        Log::info('[UpdateShift] work_hours changed — auto-dispatching FPlatform resync.', [
            'shift_id'    => $shift->id,
            'date'        => $shiftDate,
            'shift'       => $shiftNum,
            'departments' => $details->pluck('department.code')->filter()->values()->toArray(),
            'detail_ids'  => $changedDetailIds,
        ]);

        foreach ($changedDetailIds as $detailId) {
            $this->productionSyncTask->run(
                date:          $shiftDate,
                shiftNumber:   $shiftNum,
                shiftDetailId: $detailId,
            );
        }
    }
}

