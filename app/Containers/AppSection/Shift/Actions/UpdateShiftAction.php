<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Production\Tasks\SyncHourlyRecordsTask as ProductionSyncTask;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Tasks\SyncHourlyRecordsTask;
use App\Containers\AppSection\Shift\Tasks\SyncShiftDetailsTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class UpdateShiftAction extends ParentAction
{
    public function __construct(
        private readonly SyncShiftDetailsTask $syncShiftDetailsTask,
        private readonly SyncHourlyRecordsTask $syncHourlyRecordsTask,
        private readonly ProductionSyncTask $productionSyncTask,
    ) {
    }

    public function run(int $id, array $data): Shift
    {
        return DB::transaction(function () use ($id, $data) {
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
                // ── Snapshot work_hours BEFORE upsert to detect changes ──
                $beforeSnapshot = $this->snapshotWorkHours($shift);

                $this->syncShiftDetailsTask->run($shift, $data['details']);

                // Smart sync hourly records: preserve actual data, soft-delete stale
                $this->syncHourlyRecordsTask->run($shift);

                // ── Auto-resync FPlatform data for departments with changed work_hours ──
                $this->resyncChangedDepartments($shift, $beforeSnapshot, $data['details']);
            }

            return $shift->load(['details.department.productionLine', 'details.machines.machine', 'template', 'hourlyRecords']);
        });
    }

    /**
     * Snapshot the current work_hours for each ShiftDetail before upsert.
     *
     * @return array<int, float>  Map of department_id → work_hours
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
     * After upsert, compare new work_hours vs snapshot.
     * Dispatch Production FPlatform resync job for each changed department.
     */
    private function resyncChangedDepartments(Shift $shift, array $beforeSnapshot, array $detailsData): void
    {
        // Build a map of department_id → new work_hours from the payload
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
            return; // No work_hours changes — nothing to resync
        }

        // Reload ShiftDetails for changed departments (fresh data after upsert)
        $changedDetails = ShiftDetail::where('shift_id', $shift->id)
            ->whereIn('department_id', $changedDeptIds)
            ->with('department')
            ->get();

        $shiftDate  = $shift->date->toDateString();
        $shiftNum   = $shift->shift_number;

        Log::info('[UpdateShift] work_hours changed — dispatching FPlatform resync for affected departments.', [
            'shift_id'    => $shift->id,
            'date'        => $shiftDate,
            'shift'       => $shiftNum,
            'departments' => $changedDetails->pluck('department.code')->filter()->values()->toArray(),
        ]);

        foreach ($changedDetails as $detail) {
            $this->productionSyncTask->run(
                date:          $shiftDate,
                shiftNumber:   $shiftNum,
                shiftDetailId: $detail->id,
            );
        }
    }
}
