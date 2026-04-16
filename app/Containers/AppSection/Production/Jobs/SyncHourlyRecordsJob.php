<?php

namespace App\Containers\AppSection\Production\Jobs;

use App\Containers\AppSection\Production\Tasks\SyncHourlyRecordsTask;
use App\Containers\AppSection\Shift\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
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

        if (!$shift) {
            return; // No active shift today — nothing to sync
        }

        // Check if now() is within the shift time window
        if (!$this->isWithinShiftWindow($shift)) {
            Log::debug('[SyncHourlyRecords] Outside shift window — skipped.', [
                'shift' => $shift->shift_number,
                'start' => $shift->start_time,
                'end'   => $shift->end_time,
            ]);

            return;
        }

        app(SyncHourlyRecordsTask::class)->run();
    }

    /**
     * Check if current time falls within the shift's start–end window.
     *
     * Adds a buffer: starts 5 min early and ends 30 min late
     * to capture edge-case data near shift boundaries.
     */
    private function isWithinShiftWindow(Shift $shift): bool
    {
        $now = now();
        $date = $shift->date->toDateString();

        $shiftStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$shift->start_time}");
        $shiftEnd   = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$shift->end_time}");

        // Handle overnight shifts (end_time < start_time, e.g. 22:00–06:00)
        if ($shiftEnd->lte($shiftStart)) {
            $shiftEnd->addDay();
        }

        // Buffer: 5 min before start, 30 min after end
        $windowStart = $shiftStart->copy()->subMinutes(5);
        $windowEnd   = $shiftEnd->copy()->addMinutes(30);

        return $now->between($windowStart, $windowEnd);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[SyncHourlyRecords] Job failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
