<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Department\Tasks\FindDepartmentsByLineIdTask;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
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
            return ['shift' => null, 'line' => null, 'departments' => []];
        }

        $line = ProductionLine::query()
            ->where('code', $lineCode)
            ->firstOrFail();

        // Use Department container's task for clean cross-container boundary
        $departments = app(FindDepartmentsByLineIdTask::class)->run($line->id);
        $deptIds = $departments->pluck('id');

        // Single query for ALL hourly records of this line's departments
        $allRecords = HourlyRecord::query()
            ->where('shift_id', $shift->id)
            ->whereIn('department_id', $deptIds)
            ->with('hourlyMachines.machine')
            ->orderBy('hour_index')
            ->get()
            ->groupBy('department_id');

        // Batch load shift_details for all departments (1 query + machines)
        $shiftDetails = ShiftDetail::with(['machines.machine', 'latestChange'])
            ->where('shift_id', $shift->id)
            ->whereIn('department_id', $deptIds)
            ->get()
            ->keyBy('department_id');

        $departmentData = $departments->map(function ($dept) use ($allRecords, $shiftDetails) {
            $records     = $allRecords->get($dept->id, collect());
            $shiftDetail = $shiftDetails->get($dept->id);

            // Wire shiftDetail + department onto each record to avoid N+1 in transformer
            if ($shiftDetail) {
                $records->each(fn ($r) => $r->setRelation('shiftDetail', $shiftDetail));
            }
            $records->each(fn ($r) => $r->setRelation('department', $dept));

            return [
                'department'   => $dept,
                'shift_detail' => $shiftDetail,
                'hourly'       => $records,
            ];
        })->values()->all();

        return [
            'shift'       => $shift,
            'line'        => $line,
            'departments' => $departmentData,
        ];
    }
}
