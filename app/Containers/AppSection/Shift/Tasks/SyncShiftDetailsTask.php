<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Collection;

/**
 * Sync (upsert + prune) shift_details for a given shift.
 *
 * Uses upsert on unique key (shift_id, department_id, shift_number)
 * instead of delete-all + recreate, reducing N+1 queries to 2.
 *
 * For per_machine departments: also syncs shift_detail_machines pivot
 * and updates kpi_per_hour = Σ(machine KPIs).
 *
 * Hidden children (e.g., pick_dtf, pick_dtg) are auto-replicated
 * from their parent department's schedule.
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

        // Fetch existing details to preserve data for missing fields
        $existingDetails = ShiftDetail::where('shift_id', $shift->id)
            ->get()
            ->keyBy(fn ($sd) => "{$sd->department_id}_{$sd->shift_number}");

        // Prepare rows with shift_id attached, padding missing keys with existing data or null
        $rows = collect($detailsData)->map(function ($d) use ($shift, $now, $dbColumns, $existingDetails) {
            $key = $d['department_id'] . '_' . $d['shift_number'];
            $existing = $existingDetails->get($key);

            $row = [
                'shift_id'   => $shift->id,
                'updated_at' => $now,
                'created_at' => $now,
            ];

            foreach ($dbColumns as $col) {
                if (array_key_exists($col, $d)) {
                    $row[$col] = $d[$col];
                } else {
                    // Fallback to existing value, or null if it's a new record
                    $row[$col] = $existing ? $existing->{$col} : null;
                }
            }

            return $row;
        })->toArray();

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

        // Resolve hidden children once (1 query) — shared by replicate + prune
        $payloadDeptIds = collect($detailsData)->pluck('department_id')->unique()->toArray();
        $hiddenChildren = Department::whereIn('parent_id', $payloadDeptIds)
            ->where('is_hidden', true)
            ->where('is_active', true)
            ->get();

        // Auto-replicate parent schedule to hidden children
        $this->replicateToHiddenChildren($shift, $hiddenChildren, $existingDetails, $now);

        // Also protect hidden independent departments (no parent, is_hidden=true)
        // e.g. FLS Pick DTF: hidden from admin UI but has its own shift_detail from template
        $hiddenIndependentIds = Department::whereNull('parent_id')
            ->where('is_hidden', true)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        $protectedDeptIds = array_merge(
            $hiddenChildren->pluck('id')->toArray(),
            $hiddenIndependentIds,
        );

        // Prune departments removed from payload, but preserve all hidden departments
        $this->pruneRemovedDetails($shift, $detailsData, $protectedDeptIds);

        // ── Sync per_machine pivot ──
        $this->syncMachinesTask->run($shift, $detailsData, $now);
    }

    /**
     * For parent departments in the payload, upsert shift_details
     * for their hidden children (e.g., pick → pick_dtf, pick_dtg).
     *
     * Uses pre-loaded $existingDetails to avoid N+1 queries.
     */
    private function replicateToHiddenChildren(
        Shift $shift,
        Collection $hiddenChildren,
        Collection $existingDetails,
        $now,
    ): void {
        if ($hiddenChildren->isEmpty()) {
            return;
        }

        $childrenByParent = $hiddenChildren->groupBy('parent_id');

        // Re-fetch parent details after upsert to get the latest values
        $parentDetails = ShiftDetail::where('shift_id', $shift->id)
            ->whereIn('department_id', $childrenByParent->keys()->toArray())
            ->get()
            ->groupBy('department_id');

        $childRows = [];

        foreach ($parentDetails as $parentDeptId => $details) {
            $children = $childrenByParent->get($parentDeptId);
            if (!$children) {
                continue;
            }

            foreach ($details as $parentDetail) {
                foreach ($children as $child) {
                    // Use pre-loaded existing details — no extra query
                    $existingChild = $existingDetails->get("{$child->id}_{$parentDetail->shift_number}");

                    $childRows[] = [
                        'shift_id'           => $shift->id,
                        'department_id'      => $child->id,
                        'shift_number'       => $parentDetail->shift_number,
                        'headcount'          => $existingChild->headcount ?? 0,
                        'machine_count'      => null,
                        'kpi_per_hour'       => $child->kpi_per_hour ?? $parentDetail->kpi_per_hour,
                        'day_start_inventory'=> $existingChild->day_start_inventory ?? 0,
                        'start_time'         => $parentDetail->start_time,
                        'work_hours'         => $parentDetail->work_hours,
                        'prep_minutes'       => $parentDetail->prep_minutes,
                        'break1_start'       => $parentDetail->break1_start,
                        'break1_minutes'     => $parentDetail->break1_minutes,
                        'meal_break_start'   => $parentDetail->meal_break_start,
                        'meal_break_minutes' => $parentDetail->meal_break_minutes,
                        'break2_start'       => $parentDetail->break2_start,
                        'break2_minutes'     => $parentDetail->break2_minutes,
                        'break3_start'       => $parentDetail->break3_start,
                        'break3_minutes'     => $parentDetail->break3_minutes,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];
                }
            }
        }

        if (!empty($childRows)) {
            ShiftDetail::upsert(
                $childRows,
                ['shift_id', 'department_id', 'shift_number'],
                [
                    'start_time', 'work_hours', 'prep_minutes',
                    'break1_start', 'break1_minutes',
                    'meal_break_start', 'meal_break_minutes',
                    'break2_start', 'break2_minutes',
                    'break3_start', 'break3_minutes',
                    'updated_at',
                ]
            );
        }
    }

    /**
     * Remove shift_details not present in payload, but preserve hidden children.
     *
     * @param int[] $hiddenChildIds  Pre-resolved child department IDs
     */
    private function pruneRemovedDetails(Shift $shift, array $detailsData, array $hiddenChildIds): void
    {
        $keepDeptShiftPairs = collect($detailsData)->map(
            fn ($d) => [$d['department_id'], $d['shift_number']]
        );

        $keepIds = ShiftDetail::where('shift_id', $shift->id)
            ->where(function ($q) use ($keepDeptShiftPairs, $hiddenChildIds) {
                // Keep payload departments
                foreach ($keepDeptShiftPairs as $pair) {
                    $q->orWhere(function ($sub) use ($pair) {
                        $sub->where('department_id', $pair[0])
                            ->where('shift_number', $pair[1]);
                    });
                }
                // Keep hidden children
                if (!empty($hiddenChildIds)) {
                    $q->orWhereIn('department_id', $hiddenChildIds);
                }
            })
            ->pluck('id');

        ShiftDetail::where('shift_id', $shift->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
