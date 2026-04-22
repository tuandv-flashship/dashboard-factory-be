<?php

namespace App\Containers\AppSection\Production\Jobs;

use App\Containers\AppSection\Production\Services\ShiftSchedulerGuard;
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
 * Runs every minute. ShiftSchedulerGuard decides whether to execute
 * based on shift state and DB-backed configurable intervals:
 *   - In-shift  : runs every N minutes (scheduler.in_shift_interval)
 *   - Off-shift : runs every M minutes within pre/post-shift windows
 *                 (scheduler.off_shift_interval + buffer settings)
 *   - interval=0: mode disabled entirely
 *
 * Manual resync via API/Command bypasses this guard.
 * Idempotent: safe to run multiple times per hour.
 */
final class SyncHourlyRecordsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(ShiftSchedulerGuard $guard): void
    {
        if (! $guard->shouldSync()) {
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
