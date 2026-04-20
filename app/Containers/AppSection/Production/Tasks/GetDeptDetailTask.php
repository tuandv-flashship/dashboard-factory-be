<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
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

        $dept = Department::query()
            ->where('production_line_id', $line->id)
            ->where('code', $deptCode)
            ->firstOrFail();

        $records = HourlyRecord::query()
            ->where('shift_id', $shift->id)
            ->where('department_id', $dept->id)
            ->with('issues')
            ->orderBy('hour_index')
            ->get();

        $shiftDetail = ShiftDetail::query()
            ->where('shift_id', $shift->id)
            ->where('department_id', $dept->id)
            ->with(['department.productionLine'])
            ->first();

        return [
            'shift' => $shift,
            'records' => $records,
            'type' => 'department',
            'department' => $dept,
            'line' => $line,
            'shift_detail' => $shiftDetail,
        ];
    }
}

