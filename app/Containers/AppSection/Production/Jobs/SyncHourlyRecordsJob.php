<?php

namespace App\Containers\AppSection\Production\Jobs;

use App\Containers\AppSection\Production\Tasks\SyncHourlyRecordsTask;
use App\Containers\AppSection\Shift\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled Job — syncs hourly_records with FPlatform data.
 *
 * Runs every N minutes (configurable via HOURLY_RECORDS_SYNC_INTERVAL).
 * Only executes during active shift hours — skips entirely outside
 * shift time window to avoid unnecessary FPlatform queries.
 *
 * Manual resync via API/Command bypasses this guard.
 *
 * Idempotent: safe to run multiple times per hour.
 */
final class SyncHourlyRecordsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $shift = Shift::current();

        if (!$shift || !$shift->isWithinTimeWindow()) {
            return;
        }

        app(SyncHourlyRecordsTask::class)->run();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[SyncHourlyRecords] Job failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
