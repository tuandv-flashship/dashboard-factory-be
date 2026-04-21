<?php

namespace App\Containers\AppSection\Production\Traits;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Shared cache invalidation for HourlyIssue mutation tasks.
 *
 * Clears the dept-detail / line-summary / quality caches that may include
 * stale issue data after a create, update, resolve, or delete operation.
 *
 * Only historical shifts are ever cached — live data is always fresh.
 */
trait InvalidatesHourlyIssueCache
{
    /**
     * Resolve the parent HourlyRecord from an issue, then invalidate caches.
     */
    private function invalidateCacheForIssue(HourlyIssue $issue): void
    {
        // Load record + its shift + department (eager-load if not already set)
        $record = $issue->hourlyRecord ?? HourlyRecord::with('shift', 'department', 'department.productionLine')->find($issue->hourly_record_id);

        if (!$record?->shift || !$record->department) {
            return;
        }

        $shift  = $record->shift;
        $dept   = $record->department;
        $date   = $shift->date->toDateString();
        $shiftNum = $shift->shift_number;

        if (!ProductionCacheKeys::isHistorical($date)) {
            return; // Live data never cached — nothing to flush
        }

        $line = $dept->productionLine?->code;

        $keys = array_filter([
            $line ? ProductionCacheKeys::deptDetail($line, $dept->code, $date, $shiftNum) : null,
            $line ? ProductionCacheKeys::lineSummary($line, $date, $shiftNum)             : null,
            ProductionCacheKeys::quality($date, $shiftNum),
        ]);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Log::debug('[HourlyIssue] Cache invalidated after issue mutation.', [
            'issue_id' => $issue->id,
            'keys'     => array_values($keys),
        ]);
    }
}
