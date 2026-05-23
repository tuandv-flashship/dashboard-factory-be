<?php

namespace App\Containers\AppSection\Production\Services;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Containers\AppSection\Shift\Models\Shift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Determines whether SyncHourlyRecordsJob should execute based on
 * current shift status and configurable intervals.
 *
 * Two modes:
 *  - In-shift  : syncs every N minutes while shift isWithinTimeWindow()
 *  - Off-shift : syncs every M minutes during pre/post-shift windows
 *                derived dynamically from today's actual shift schedule
 *
 * All interval/buffer values are read from the DB settings table
 * (key prefix: scheduler.*), falling back to config/factory.php.
 *
 * Cache TTL = 1 hour per setting key. Cache is busted immediately by
 * UpdateProductionSchedulerSettingsAction on every admin update.
 */
final class ShiftSchedulerGuard
{
    // ── Cache key constants ──────────────────────────────────────────

    public const SETTING_CACHE_PREFIX  = 'factory:scheduler_setting:';
    public const IN_SHIFT_LOCK         = 'factory:last_in_shift_sync';
    public const OFF_SHIFT_LOCK        = 'factory:last_off_shift_sync';

    public const SETTING_IN_SHIFT_INTERVAL          = 'scheduler.in_shift_interval';
    public const SETTING_OFF_SHIFT_INTERVAL         = 'scheduler.off_shift_interval';
    public const SETTING_OFF_SHIFT_BEFORE_MINUTES   = 'scheduler.off_shift_before_minutes';
    public const SETTING_OFF_SHIFT_AFTER_MINUTES    = 'scheduler.off_shift_after_minutes';
    public const SETTING_END_OF_DAY_SYNC_AT          = 'scheduler.end_of_day_sync_at';

    /** All setting keys managed by this guard (used for cache invalidation). */
    public const ALL_SETTING_KEYS = [
        self::SETTING_IN_SHIFT_INTERVAL,
        self::SETTING_OFF_SHIFT_INTERVAL,
        self::SETTING_OFF_SHIFT_BEFORE_MINUTES,
        self::SETTING_OFF_SHIFT_AFTER_MINUTES,
        self::SETTING_END_OF_DAY_SYNC_AT,
    ];

    /** Short-lived cache for today's shifts — avoids 1 DB query/minute during off-shift. */
    private const SHIFTS_TODAY_TTL = 300; // 5 minutes

    // ── Public API ───────────────────────────────────────────────────

    /**
     * Returns true if a sync should be executed right now.
     *
     * In-shift:  respects in_shift_interval
     * Off-shift: respects off_shift_interval within pre/post-shift windows
     * interval=0: disables that mode entirely
     */
    public function shouldSync(): bool
    {
        $shift = Shift::current();

        if ($shift && $shift->isWithinTimeWindow()) {
            return $this->checkInterval(self::IN_SHIFT_LOCK, $this->inShiftInterval());
        }

        return $this->checkOffShiftWindow();
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Checks if now() falls in any pre-shift or post-shift window
     * for today's shifts, then rate-limits by off_shift_interval.
     */
    private function checkOffShiftWindow(): bool
    {
        $interval = $this->offShiftInterval();

        if ($interval <= 0) {
            return false;
        }

        $beforeBuffer = $this->offShiftBeforeMinutes();
        $afterBuffer  = $this->offShiftAfterMinutes();

        $now    = now();
        $date   = $now->toDateString();
        $shifts = Cache::remember(
            "factory:shifts_today:{$date}",
            self::SHIFTS_TODAY_TTL,
            fn () => Shift::with('details')->where('date', $date)->orderBy('shift_number')->get(),
        );

        foreach ($shifts as $shift) {
            $shiftStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$shift->start_time}");

            // Use computeEndAt() → accounts for departments running past shift's own end_time
            $shiftEnd = $shift->computeEndAt();

            $inPreWindow  = $now->between($shiftStart->copy()->subMinutes($beforeBuffer), $shiftStart);
            $inPostWindow = $now->between($shiftEnd, $shiftEnd->copy()->addMinutes($afterBuffer));

            if ($inPreWindow || $inPostWindow) {
                return $this->checkInterval(self::OFF_SHIFT_LOCK, $interval);
            }
        }

        return false;
    }

    /**
     * Rate-limits execution using a cache flag with TTL = interval.
     * Returns true (and sets the flag) only when the flag is absent.
     */
    private function checkInterval(string $lockKey, int $intervalMinutes): bool
    {
        if ($intervalMinutes <= 0 || Cache::has($lockKey)) {
            return false;
        }

        Cache::put($lockKey, true, now()->addMinutes($intervalMinutes));

        return true;
    }

    // ── Settings readers (DB → cache → config fallback) ──────────────

    private function setting(string $key, mixed $default): mixed
    {
        return Cache::remember(
            self::SETTING_CACHE_PREFIX . $key,
            now()->addHour(),
            fn () => Setting::query()->where('key', $key)->value('value') ?? $default,
        );
    }

    private function inShiftInterval(): int
    {
        return (int) $this->setting(
            self::SETTING_IN_SHIFT_INTERVAL,
            config('factory.hourly_records_sync_interval', 5),
        );
    }

    private function offShiftInterval(): int
    {
        return (int) $this->setting(
            self::SETTING_OFF_SHIFT_INTERVAL,
            config('factory.off_shift_sync_interval', 15),
        );
    }

    private function offShiftBeforeMinutes(): int
    {
        return (int) $this->setting(
            self::SETTING_OFF_SHIFT_BEFORE_MINUTES,
            config('factory.off_shift_before_minutes', 120),
        );
    }

    private function offShiftAfterMinutes(): int
    {
        return (int) $this->setting(
            self::SETTING_OFF_SHIFT_AFTER_MINUTES,
            config('factory.off_shift_after_minutes', 180),
        );
    }

    /**
     * Time to run end-of-day final sync (HH:MM format).
     * Read from DB setting → cache → config fallback.
     */
    public function endOfDaySyncAt(): string
    {
        return (string) $this->setting(
            self::SETTING_END_OF_DAY_SYNC_AT,
            config('factory.end_of_day_sync_at', '23:55'),
        );
    }
}
