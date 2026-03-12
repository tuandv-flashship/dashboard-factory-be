<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\PickHourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Production\Models\Shift;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class GetLineSummaryTask extends ParentTask
{
    /**
     * Get summary data for a specific production line.
     * Supports historical queries via optional date + shift_number.
     */
    public function run(string $lineCode, ?string $date = null, ?int $shiftNumber = null): array
    {
        $shift = Shift::resolve($date, $shiftNumber);
        if (!$shift) {
            return ['shift' => null, 'line' => null, 'departments' => [], 'pick' => null];
        }

        $line = ProductionLine::query()
            ->where('code', $lineCode)
            ->with('departments')
            ->firstOrFail();

        $deptIds = $line->departments->pluck('id');

        // Single query for ALL hourly records of this line's departments
        $allRecords = HourlyRecord::query()
            ->where('shift_id', $shift->id)
            ->whereIn('department_id', $deptIds)
            ->orderBy('hour_index')
            ->get()
            ->groupBy('department_id');

        $departments = $line->departments->map(function ($dept) use ($allRecords) {
            $records = $allRecords->get($dept->id, collect());
            return [
                'department' => $dept,
                'hourly' => $records,
                'staff' => $records->first()?->staff ?? 0,
                'efficiency' => $records->first()?->efficiency ?? 0,
                'error_rate' => $records->first()?->error_rate ?? 0,
            ];
        })->values()->all();

        // Pick data for this line
        $pickRecords = PickHourlyRecord::query()
            ->where('shift_id', $shift->id)
            ->where('production_line_id', $line->id)
            ->orderBy('hour_index')
            ->get();

        return [
            'shift' => $shift,
            'line' => $line,
            'departments' => $departments,
            'pick' => $pickRecords,
        ];
    }
}

