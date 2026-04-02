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
 */
final class SyncShiftDetailsTask extends ParentTask
{
    public function run(Shift $shift, array $detailsData): void
    {
        $now = now();

        // Prepare rows with shift_id attached
        $rows = collect($detailsData)->map(fn ($d) => array_merge($d, [
            'shift_id'   => $shift->id,
            'updated_at' => $now,
            'created_at' => $now,
        ]))->toArray();

        // Single upsert query on unique key (shift_id, department_id, shift_number)
        ShiftDetail::upsert(
            $rows,
            ['shift_id', 'department_id', 'shift_number'],
            [
                'headcount', 'kpi_per_hour', 'day_start_inventory',
                'start_time', 'work_hours', 'prep_minutes',
                'break1_start', 'break1_minutes',
                'meal_break_start', 'meal_break_minutes',
                'break2_start', 'break2_minutes',
                'break3_start', 'break3_minutes',
                'updated_at',
            ]
        );

        // Prune departments removed from the payload
        $keepPairs = collect($detailsData)->map(
            fn ($d) => $d['department_id'] . '|' . $d['shift_number']
        );

        ShiftDetail::where('shift_id', $shift->id)
            ->get()
            ->reject(fn ($d) => $keepPairs->contains(
                $d->department_id . '|' . $d->shift_number
            ))
            ->each->delete();
    }
}
