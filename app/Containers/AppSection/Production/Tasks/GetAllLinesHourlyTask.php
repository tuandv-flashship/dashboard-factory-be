<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Department\Tasks\FindDepartmentsByLineIdTask;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hourly records for ALL lines, grouped by line → department → hourly[].
 *
 * Structure: lines[] → departments[] → hourly[]
 *
 * Supports historical queries via optional date + shift_number.
 * Uses Shift::resolve() for shift resolution (same as GetLineSummaryTask).
 */
final class GetAllLinesHourlyTask extends ParentTask
{
    public function run(?string $date = null, ?int $shiftNumber = null): array
    {
        $shift = Shift::resolve($date, $shiftNumber);
        if (!$shift) {
            return ['shift' => null, 'lines' => []];
        }

        $lines = ProductionLine::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Single query: ALL hourly records for this shift (all departments)
        $allRecords = HourlyRecord::where('shift_id', $shift->id)
            ->orderBy('hour_index')
            ->get()
            ->groupBy('department_id');

        $lineData = [];

        foreach ($lines as $line) {
            $departments = app(FindDepartmentsByLineIdTask::class)->run($line->id);

            $deptData = $departments->map(function ($dept) use ($allRecords) {
                $records = $allRecords->get($dept->id, collect());

                return [
                    'department' => $dept,
                    'hourly'    => $records,
                ];
            })->values()->all();

            $lineData[] = [
                'line'        => $line,
                'departments' => $deptData,
            ];
        }

        return [
            'shift' => $shift,
            'lines' => $lineData,
        ];
    }
}
