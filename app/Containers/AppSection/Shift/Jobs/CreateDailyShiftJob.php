<?php

namespace App\Containers\AppSection\Shift\Jobs;

use App\Containers\AppSection\Production\Services\ShiftSchedulerGuard;
use App\Containers\AppSection\Shift\Actions\CreateDailyShiftAction;
use App\Containers\AppSection\Setting\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled Job — runs daily to auto-create shift 1 and refresh day-start inventory.
 *
 * Fires every minute; self-guards via two layers:
 *   1. Time check  : only executes when now(tz) matches scheduler.daily_shift_job_at
 *   2. Dedup cache : prevents duplicate runs within the same calendar day
 *
 * Configured time is read from the DB settings table (key: scheduler.daily_shift_job_at),
 * falling back to config('factory.daily_shift_job_at', '04:50'). Cache is busted
 * immediately by UpdateProductionSchedulerSettingsAction — no scheduler restart needed.
 *
 * Idempotent: CreateDailyShiftAction handles already-created shifts gracefully.
 */
final class CreateDailyShiftJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const DEDUP_KEY_PREFIX = 'factory:daily_shift_created:';

    public function handle(): void
    {
        $tz = config('app.timezone');

        // Guard 1: not the configured run time yet
        $targetTime = Cache::remember(
            ShiftSchedulerGuard::SETTING_CACHE_PREFIX . 'scheduler.daily_shift_job_at',
            now($tz)->addHour(),
            fn () => Setting::query()
                ->where('key', 'scheduler.daily_shift_job_at')
                ->value('value')
                ?? config('factory.daily_shift_job_at', '04:50'),
        );

        if (now($tz)->format('H:i') !== $targetTime) {
            return;
        }

        // Guard 2: already ran today
        $dedupKey = self::DEDUP_KEY_PREFIX . now($tz)->toDateString();

        if (Cache::has($dedupKey)) {
            return;
        }

        // Reserve slot before running to prevent race conditions
        Cache::put($dedupKey, true, Carbon::tomorrow($tz));

        $result = app(CreateDailyShiftAction::class)->run();

        match ($result['status']) {
            'created'           => Log::info("[CreateDailyShift] {$result['message']}"),
            'inventory_updated' => Log::info("[CreateDailyShift] {$result['message']}"),
            'skipped'           => Log::info("[CreateDailyShift] {$result['message']}"),
            'no_template'       => Log::warning("[CreateDailyShift] {$result['message']}"),
        };
    }

    public function failed(\Throwable $e): void
    {
        // Clear dedup key so the job can re-run on the next scheduler tick
        Cache::forget(self::DEDUP_KEY_PREFIX . now(config('app.timezone'))->toDateString());

        Log::error('[CreateDailyShift] Job failed', ['error' => $e->getMessage()]);
    }
}
