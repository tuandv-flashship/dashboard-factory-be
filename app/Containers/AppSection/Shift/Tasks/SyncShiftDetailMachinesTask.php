<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftDetailMachine;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Sync shift_detail_machines pivot for per_machine departments.
 */
final class SyncShiftDetailMachinesTask extends ParentTask
{
    /**
     * @param Shift $shift
     * @param array $detailsData Array of shift detail payloads. Must include 'department_id', 'shift_number', and optionally 'machine_ids'.
     * @param \DateTimeInterface|null $now
     */
    public function run(Shift $shift, array $detailsData, ?\DateTimeInterface $now = null): void
    {
        $now = $now ?? now();

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
                // No machines → kpi = 0, machine_count = 0
                $shiftDetail->update(['kpi_per_hour' => 0, 'machine_count' => 0]);
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

            // Update kpi_per_hour = Σ(machine KPI) and machine_count = valid machine count
            $shiftDetail->update([
                'kpi_per_hour'  => $totalKpi,
                'machine_count' => count($pivotRows),
            ]);
        }
    }
}
