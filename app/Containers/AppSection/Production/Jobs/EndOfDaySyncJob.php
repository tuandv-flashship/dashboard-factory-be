<?php

namespace App\Containers\AppSection\Production\Jobs;

use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Production\Tasks\SyncHourlyRecordsTask;
use App\Containers\AppSection\Shift\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * End-of-day final sync — captures latest FPlatform data before the day rolls over.
 *
 * Runs once daily at a configurable time (default 23:55, setting: scheduler.end_of_day_sync_at).
 * Force-syncs ALL shifts of the current day with forceAll=true, bypassing
 * the end-time guard so every department gets fresh data regardless of
 * when their shift ended.
 *
 * This ensures order status changes (CANCELED, HOLD, etc.) and late
 * scan_label_history entries that occurred after the off-shift sync
 * window are captured in hourly_records and order_summaries.
 *
 * Idempotent: safe to run multiple times — SyncHourlyRecordsTask
 * handles upsert logic internally.
 */
final class EndOfDaySyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $today = now()->toDateString();

        $shifts = Shift::where('date', $today)
            ->orderBy('shift_number')
            ->get();

        if ($shifts->isEmpty()) {
            Log::info('[EndOfDaySync] No shifts found for today — skipped.', [
                'date' => $today,
            ]);

            return;
        }

        Log::info("[EndOfDaySync] Starting end-of-day sync.", [
            'date'   => $today,
            'shifts' => $shifts->pluck('shift_number')->toArray(),
        ]);

        $task = app(SyncHourlyRecordsTask::class);

        foreach ($shifts as $shift) {
            try {
                $result = $task->run(
                    date: $today,
                    shiftNumber: $shift->shift_number,
                    forceAll: true,
                );

                Log::info("[EndOfDaySync] Shift {$shift->shift_number} dispatched.", [
                    'date'    => $today,
                    'message' => $result['message'] ?? '',
                ]);

                // Flush order-summary cache so next request gets fresh data
                Cache::forget(
                    ProductionCacheKeys::orderSummary($today, $shift->shift_number)
                );
            } catch (\Throwable $e) {
                Log::warning("[EndOfDaySync] Shift {$shift->shift_number} failed.", [
                    'date'  => $today,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[EndOfDaySync] Job failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
