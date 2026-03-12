<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\Department;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\PickHourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Production\Models\Shift;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class GetDeptDetailTask extends ParentTask
{
    /**
     * Get hourly records with issues for a specific line+dept.
     * Supports historical queries via optional date + shift_number.
     */
    public function run(string $lineCode, string $deptCode, ?string $date = null, ?int $shiftNumber = null): array
    {
        $shift = Shift::resolve($date, $shiftNumber);
        if (!$shift) {
            return ['shift' => null, 'records' => collect(), 'summary' => null];
        }

        $line = ProductionLine::query()->where('code', $lineCode)->firstOrFail();

        // Pick department uses a separate table
        if ($deptCode === 'pick') {
            $records = PickHourlyRecord::query()
                ->where('shift_id', $shift->id)
                ->where('production_line_id', $line->id)
                ->orderBy('hour_index')
                ->get();

            return [
                'shift' => $shift,
                'records' => $records,
                'type' => 'pick',
                'line' => $line,
            ];
        }

        // Regular departments
        $dept = Department::query()
            ->where('production_line_id', $line->id)
            ->where('code', $deptCode === 'dtg_print' ? 'print' : $deptCode)
            ->firstOrFail();

        $records = HourlyRecord::query()
            ->where('shift_id', $shift->id)
            ->where('department_id', $dept->id)
            ->with('issues')
            ->orderBy('hour_index')
            ->get();

        return [
            'shift' => $shift,
            'records' => $records,
            'type' => 'department',
            'department' => $dept,
            'line' => $line,
        ];
    }
}
