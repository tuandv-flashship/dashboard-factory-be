<?php

namespace App\Containers\AppSection\Production\Jobs;

use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Shift\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Daily finalization: mark all non-completed records of past shifts as completed.
 *
 * Runs once daily (e.g. 01:00 AM) to catch any records that were not
 * finalized during the post-shift sync window.
 *
 * Idempotent: safe to run multiple times — only updates rows
 * whose status is still pending/active.
 */
final class FinalizeShiftRecordsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $staleQuery = HourlyRecord::query()
            ->whereHas('shift', fn ($q) => $q->where('date', '<', today())
                                              ->where('date', '>=', today()->subDays(7)))
            ->where('status', '!=', HourlyRecordStatus::Completed->value);

        // Collect affected shift IDs BEFORE the update — after update they'd be indistinguishable.
        $affectedShiftIds = (clone $staleQuery)->distinct()->pluck('shift_id');

        $updated = $staleQuery->update(['status' => HourlyRecordStatus::Completed->value]);

        if ($updated > 0) {
            Log::info("[FinalizeShiftRecords] Finalized {$updated} stale records.", [
                'shift_ids' => $affectedShiftIds->toArray(),
            ]);

            // Flush cached responses only for truly affected shifts.
            Shift::whereIn('id', $affectedShiftIds)
                ->get()
                ->each(fn (Shift $shift) => ProductionCacheKeys::flushForShift($shift));
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[FinalizeShiftRecords] Job failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
