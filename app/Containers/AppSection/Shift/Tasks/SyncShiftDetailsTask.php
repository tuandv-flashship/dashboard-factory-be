<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftDetailMachine;
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
    public function run(Shift $shift, array $detailsData): void
    {
        $now = now();

        // Strip non-DB keys (machine_ids is handled separately in syncMachines)
        $dbColumns = [
            'department_id', 'shift_number', 'headcount', 'kpi_per_hour',
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
                'headcount', 'kpi_per_hour',
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
        $this->syncMachines($shift, $detailsData, $now);
    }

    /**
     * Sync shift_detail_machines pivot for per_machine departments.
     */
    private function syncMachines(Shift $shift, array $detailsData, \DateTimeInterface $now): void
    {
        // Identify departments with machine_ids in payload
        $machineEntries = collect($detailsData)->filter(
            fn ($d) => isset($d['machine_ids']) && is_array($d['machine_ids'])
        );

        if ($machineEntries->isEmpty()) {
            return;
        }

        // Batch load ALL referenced machines in a single query
        $allMachineIds = $machineEntries->flatMap(fn ($d) => $d['machine_ids'])->unique()->toArray();
        $allMachines = !empty($allMachineIds)
            ? Machine::whereIn('id', $allMachineIds)->get()->keyBy('id')
            : collect();

        // Re-fetch the shift_details for these departments
        $deptIds = $machineEntries->pluck('department_id')->unique()->toArray();

        $shiftDetails = ShiftDetail::where('shift_id', $shift->id)
            ->whereIn('department_id', $deptIds)
            ->get()
            ->keyBy(fn ($sd) => "{$sd->department_id}|{$sd->shift_number}");

        foreach ($machineEntries as $entry) {
            $key = "{$entry['department_id']}|{$entry['shift_number']}";
            $shiftDetail = $shiftDetails->get($key);
            if (!$shiftDetail) {
                continue;
            }

            $machineIds = $entry['machine_ids'];

            // Delete old pivot records for this shift_detail
            ShiftDetailMachine::where('shift_detail_id', $shiftDetail->id)->delete();

            if (empty($machineIds)) {
                // No machines → kpi = 0
                $shiftDetail->update(['kpi_per_hour' => 0]);
                continue;
            }

            // Filter from pre-loaded collection (safety: only from matching department)
            $pivotRows = [];
            $totalKpi = 0;

            foreach ($machineIds as $machineId) {
                $machine = $allMachines->get($machineId);
                if (!$machine || $machine->department_id !== $entry['department_id']) {
                    continue;
                }
                $pivotRows[] = [
                    'shift_detail_id' => $shiftDetail->id,
                    'machine_id'      => $machine->id,
                    'kpi_per_hour'    => $machine->kpi_per_hour,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
                $totalKpi += $machine->kpi_per_hour;
            }

            if (!empty($pivotRows)) {
                ShiftDetailMachine::insert($pivotRows);
            }

            $shiftDetail->update(['kpi_per_hour' => $totalKpi]);
        }
    }
}
