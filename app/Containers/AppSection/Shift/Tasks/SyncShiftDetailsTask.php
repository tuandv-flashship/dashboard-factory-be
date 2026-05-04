<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Sync (upsert + prune) shift_details for a given shift.
 *
 * Uses upsert on unique key (shift_id, department_id, shift_number)
 * instead of delete-all + recreate, reducing N+1 queries to 2.
 *
 * For per_machine departments: also syncs shift_detail_machines pivot
 * and updates kpi_per_hour = Σ(machine KPIs).
 */
final class SyncShiftDetailsTask extends ParentTask
{
    public function __construct(
        private readonly SyncShiftDetailMachinesTask $syncMachinesTask
    ) {
    }

    public function run(Shift $shift, array $detailsData): void
    {
        $now = now();

        // Strip non-DB keys (machine_ids is handled separately in syncMachines)
        $dbColumns = [
            'department_id', 'shift_number', 'headcount', 'machine_count',
            'start_time', 'work_hours', 'prep_minutes',
            'break1_start', 'break1_minutes', 'meal_break_start', 'meal_break_minutes',
            'break2_start', 'break2_minutes', 'break3_start', 'break3_minutes',
        ];

        // Prepare rows with shift_id attached, stripping non-DB keys
        $rows = collect($detailsData)->map(fn ($d) => array_merge(
            collect($d)->only($dbColumns)->toArray(),
            [
                'shift_id'   => $shift->id,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        ))->toArray();

        // Single upsert query on unique key (shift_id, department_id, shift_number)
        ShiftDetail::upsert(
            $rows,
            ['shift_id', 'department_id', 'shift_number'],
            [
                'headcount', 'machine_count',
                'start_time', 'work_hours', 'prep_minutes',
                'break1_start', 'break1_minutes',
                'meal_break_start', 'meal_break_minutes',
                'break2_start', 'break2_minutes',
                'break3_start', 'break3_minutes',
                'updated_at',
            ]
        );

        // Prune departments removed from the payload (2 DB queries instead of load-all + N deletes)
        $keepDeptShiftPairs = collect($detailsData)->map(
            fn ($d) => [$d['department_id'], $d['shift_number']]
        );

        $keepIds = ShiftDetail::where('shift_id', $shift->id)
            ->where(function ($q) use ($keepDeptShiftPairs) {
                foreach ($keepDeptShiftPairs as $pair) {
                    $q->orWhere(function ($sub) use ($pair) {
                        $sub->where('department_id', $pair[0])
                            ->where('shift_number', $pair[1]);
                    });
                }
            })
            ->pluck('id');

        ShiftDetail::where('shift_id', $shift->id)
            ->whereNotIn('id', $keepIds)
            ->delete();

        // ── Sync per_machine pivot ──
        $this->syncMachinesTask->run($shift, $detailsData, $now);
    }
}
