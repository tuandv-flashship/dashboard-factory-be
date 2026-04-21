<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Collection;

final class GetPendingIssuesTask extends ParentTask
{
    /**
     * Return all unresolved HourlyIssues for a given shift, optionally filtered by department.
     *
     * @return Collection<HourlyIssue> each with eager-loaded hourlyRecord.shift + hourlyRecord.department
     */
    public function run(?string $date = null, ?int $shiftNumber = null, ?int $departmentId = null): Collection
    {
        $shift = ($date || $shiftNumber)
            ? Shift::resolve($date, $shiftNumber)
            : Shift::current();

        if (!$shift) {
            return collect();
        }

        // Fetch hourly record IDs for this shift
        $recordQuery = HourlyRecord::where('shift_id', $shift->id);

        if ($departmentId) {
            $recordQuery->where('department_id', $departmentId);
        }

        $recordIds = $recordQuery->pluck('id');

        return HourlyIssue::whereIn('hourly_record_id', $recordIds)
            ->whereNull('resolved_at')
            ->with([
                'hourlyRecord',
                'hourlyRecord.department',
            ])
            ->orderBy('hourly_record_id')
            ->get();
    }
}
