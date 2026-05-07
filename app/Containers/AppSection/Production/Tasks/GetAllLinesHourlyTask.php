<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hourly records for ALL lines, grouped by line → department → hourly[].
 *
 * Structure: lines[] → departments[] → { department, shift_detail, hourly[] }
 *
 * Supports historical queries via optional date + shift_number.
 * Uses Shift::resolve() for shift resolution.
 *
 * Optimised: 4 queries total (lines+depts, hourly, shift_details, machines).
 */
final class GetAllLinesHourlyTask extends ParentTask
{
    public function run(?string $date = null, ?int $shiftNumber = null): array
    {
        $shift = Shift::resolve($date, $shiftNumber);
        if (!$shift) {
            return ['shift' => null, 'lines' => []];
        }

        // Eager-load template so ShiftTransformer can populate template_* fields
        $shift->load('template');

        // 1 query: lines + departments (eager-loaded) + department.machines
        $lines = ProductionLine::where('is_active', true)
            ->with(['departments' => fn ($q) => $q->orderBy('sort_order'), 'departments.machines'])
            ->orderBy('sort_order')
            ->get();

        // 1 query: ALL hourly records for this shift, grouped by department
        $allRecords = HourlyRecord::where('shift_id', $shift->id)
            ->with('hourlyMachines.machine')
            ->orderBy('hour_index')
            ->get()
            ->groupBy('department_id');

        // 1 query (+1 for machines): ALL shift details for this shift, keyed by department
        $allDetails = ShiftDetail::where('shift_id', $shift->id)
            ->with(['department.productionLine', 'machines.machine'])
            ->get()
            ->keyBy('department_id');

        $lineData = $lines->map(function (ProductionLine $line) use ($allRecords, $allDetails) {
            $deptData = $line->departments->map(function ($dept) use ($allRecords, $allDetails) {
                $detail  = $allDetails->get($dept->id);
                $records = $allRecords->get($dept->id, collect());

                // Wire shiftDetail + department onto each record to avoid N+1 in transformer
                if ($detail) {
                    $records->each(fn ($r) => $r->setRelation('shiftDetail', $detail));
                }
                $records->each(fn ($r) => $r->setRelation('department', $dept));

                return [
                    'department'   => $dept,
                    'shift_detail' => $detail,
                    'hourly'       => $records,
                ];
            })->values()->all();

            return [
                'line'        => $line,
                'departments' => $deptData,
            ];
        })->values()->all();

        return [
            'shift' => $shift,
            'lines' => $lineData,
        ];
    }
}
