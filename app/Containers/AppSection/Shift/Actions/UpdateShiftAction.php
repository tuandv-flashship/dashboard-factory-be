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
                // Snapshot schedule BEFORE upsert to detect changes
                $beforeSnapshot = $this->snapshotSchedule($shift);

                $this->syncShiftDetailsTask->run($shift, $data['details']);

                // Smart sync hourly records: preserve actual data, soft-delete stale
                $this->syncHourlyRecordsTask->run($shift);

                // ── Recalculate Shift header end_time = max dept end_time ──
                $this->recalculateShiftEndTime($shift);

                // Detect which ShiftDetails had schedule changed (work_hours or start_time)
                $changedDetailIds = $this->detectChangedDetails($shift, $beforeSnapshot, $data['details']);
            }

            return $shift->load(['details.department.productionLine', 'details.machines.machine', 'details.latestChange', 'template', 'hourlyRecords']);
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
     * Snapshot the current schedule for each ShiftDetail before upsert.
     *
     * Captures all fields that affect the FPlatform query range:
     * work_hours + start_time → deptStart, meal_break_minutes → deptEnd.
     *
     * @return array<int, array{work_hours: float, start_time: string, meal_break_minutes: int}>  department_id → schedule
     */
    private function snapshotSchedule(Shift $shift): array
    {
        return ShiftDetail::where('shift_id', $shift->id)
            ->get()
            ->keyBy('department_id')
            ->map(fn ($d) => [
                'work_hours'          => (float) $d->work_hours,
                'start_time'          => $d->start_time,
                'meal_break_minutes'  => (int) ($d->meal_break_minutes ?? 0),
            ])
            ->toArray();
    }

    /**
     * Compare new schedule payload vs snapshot and return changed ShiftDetail IDs.
     *
     * Detects changes in both work_hours AND start_time, since either change
     * shifts the FPlatform query range and requires a resync.
     *
     * @return int[]
     */
    private function detectChangedDetails(Shift $shift, array $beforeSnapshot, array $detailsData): array
    {
        $changedDeptIds = [];

        foreach ($detailsData as $detail) {
            $deptId = (int) ($detail['department_id'] ?? 0);
            if (!$deptId || !isset($beforeSnapshot[$deptId])) {
                continue;
            }

            $old = $beforeSnapshot[$deptId];

            // Check work_hours change
            if (isset($detail['work_hours']) && abs($old['work_hours'] - (float) $detail['work_hours']) > 0.001) {
                $changedDeptIds[] = $deptId;
                continue;
            }

            // Check start_time change
            if (isset($detail['start_time']) && $old['start_time'] !== $detail['start_time']) {
                $changedDeptIds[] = $deptId;
                continue;
            }

            // Check meal_break_minutes change (affects deptEnd → FPlatform query range)
            if (isset($detail['meal_break_minutes']) && $old['meal_break_minutes'] !== (int) $detail['meal_break_minutes']) {
                $changedDeptIds[] = $deptId;
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

