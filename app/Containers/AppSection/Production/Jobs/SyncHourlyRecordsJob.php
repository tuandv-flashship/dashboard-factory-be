<?php

namespace App\Containers\AppSection\Production\Jobs;

use App\Containers\AppSection\Production\Tasks\SyncHourlyRecordsTask;
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
 * Updates actual, staff, hour_start_inventory, efficiency for the
 * current hour slot of each department in the active shift.
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
        app(SyncHourlyRecordsTask::class)->run();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[SyncHourlyRecords] Job failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
