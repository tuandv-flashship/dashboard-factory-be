<?php

namespace App\Containers\AppSection\Production\Support;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use Illuminate\Support\Facades\Cache;

/**
 * Centralized cache key definitions for production dashboard endpoints.
 *
 * Single source of truth — used by both API controllers (to read/write)
 * and SyncDepartmentHourlyJob (to invalidate after sync).
 *
 * Only historical dates (strictly < today) are ever cached.
 * Today's live data is never cached so the sync job always shows
 * fresh numbers without needing to flush anything for the current day.
 */
final class ProductionCacheKeys
{
    /** 1-hour TTL for historical snapshots (data won't change after the day ends). */
    public const TTL_HISTORICAL = 3600; // seconds

    // ── Key builders ────────────────────────────────────────────────

    /**
     * dept-detail:{line}:{dept}:{date}:{shift}
     * Used by: GetDeptDetailController, SyncDepartmentHourlyJob
     */
    public static function deptDetail(string $line, string $dept, string $date, int|string $shift): string
    {
        return "dept-detail:{$line}:{$dept}:{$date}:{$shift}";
    }

    /**
     * line-summary:{line}:{date}:{shift}
     * Used by: GetLineSummaryController, SyncDepartmentHourlyJob
     */
    public static function lineSummary(string $line, string $date, int|string $shift): string
    {
        return "line-summary:{$line}:{$date}:{$shift}";
    }

    /**
     * quality:{date}:{shift}
     * Used by: GetQualityDataController, SyncDepartmentHourlyJob
     */
    public static function quality(string $date, int|string $shift): string
    {
        return "quality:{$date}:{$shift}";
    }

    /**
     * Base key prefix for all-lines-hourly cache.
     * Used internally for version counter key.
     */
    public static function allLinesHourly(string $date, int|string $shift): string
    {
        return "all-lines-hourly:{$date}:{$shift}";
    }

    /**
     * Scope-aware, versioned cache key for all-lines-hourly.
     *
     * Key format: all-lines-hourly:{date}:{shift}:v{version}:s{scopeHash}
     *
     * - scopeHash: md5 of sorted dept IDs (or 'global' for admins)
     *   → users with identical department_scope share the same cache entry.
     * - version: auto-incremented on flush → all old entries become orphaned
     *   and expire naturally via TTL (2min today / 1hr historical).
     *
     * Used by: GetAllLinesHourlyController
     *
     * @param int[]|null $scopedDeptIds  null = global access, [] = no access, [1,2,...] = scoped
     */
    public static function allLinesHourlyScoped(string $date, int|string $shift, ?array $scopedDeptIds): string
    {
        $base = self::allLinesHourly($date, $shift);
        $ver  = (int) Cache::get("{$base}:ver", 0);

        if ($scopedDeptIds === null) {
            $scopeHash = 'global';
        } else {
            $sorted = $scopedDeptIds;
            sort($sorted);
            $scopeHash = md5(implode(',', $sorted));
        }

        return "{$base}:v{$ver}:s{$scopeHash}";
    }

    /**
     * order-summary:{date}:{shift}
     * Used by: GetOrderSummaryController, SyncOrderInventoryTask (invalidation)
     */
    public static function orderSummary(string $date, int|string $shift): string
    {
        return "order-summary:{$date}:{$shift}";
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * Return true when the given date qualifies for caching
     * (strictly in the past — not today, not future).
     */
    public static function isHistorical(?string $date): bool
    {
        return $date !== null && $date < now()->toDateString();
    }

    // ── Flush helpers ───────────────────────────────────────────────

    /**
     * Flush caches for a single department within a shift.
     *
     * Used by hourly record create/update/delete controllers.
     */
    public static function flushForDepartment(Shift|int $shift, Department|int $department): void
    {
        $shift = $shift instanceof Shift ? $shift : Shift::find($shift);
        $dept  = $department instanceof Department ? $department : Department::with('productionLine')->find($department);

        if (!$shift || !$dept) {
            return;
        }

        // Ensure productionLine is loaded
        if (!$dept->relationLoaded('productionLine')) {
            $dept->load('productionLine');
        }

        $date     = $shift->date->toDateString();
        $shiftNum = $shift->shift_number;

        // Always flush allLinesHourly (version bump) and orderSummary
        self::flushAllLinesHourly($date, $shiftNum);
        Cache::forget(self::orderSummary($date, $shiftNum));

        if (!self::isHistorical($date)) {
            return;
        }

        $line = $dept->productionLine?->code;

        $keys = array_filter([
            $line ? self::deptDetail($line, $dept->code, $date, $shiftNum) : null,
            $line ? self::lineSummary($line, $date, $shiftNum)             : null,
            self::quality($date, $shiftNum),
        ]);

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Flush all production caches for every department in a shift.
     *
     * Used by resync controller/command and UpdateShiftAction.
     */
    public static function flushForShift(Shift $shift): void
    {
        $date     = $shift->date->toDateString();
        $shiftNum = $shift->shift_number;

        // Bump version for allLinesHourly (scope-aware, can't enumerate keys)
        self::flushAllLinesHourly($date, $shiftNum);

        $keys = [
            self::orderSummary($date, $shiftNum),
        ];

        if (!self::isHistorical($date)) {
            foreach (array_unique($keys) as $key) {
                Cache::forget($key);
            }

            return;
        }

        $details = ShiftDetail::with('department.productionLine')
            ->where('shift_id', $shift->id)
            ->get();

        foreach ($details as $detail) {
            $line = $detail->department?->productionLine?->code;
            $code = $detail->department?->code;

            if ($line && $code) {
                $keys[] = self::deptDetail($line, $code, $date, $shiftNum);
                $keys[] = self::lineSummary($line, $date, $shiftNum);
            }
        }

        $keys[] = self::quality($date, $shiftNum);

        $keys = array_unique($keys);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Invalidate all scope-specific allLinesHourly cache entries for a date+shift.
     *
     * Instead of enumerating all scope hashes, we increment a version counter.
     * Old cache entries (with the previous version) become orphaned and expire
     * naturally via their TTL (2min for today, 1hr for historical).
     */
    public static function flushAllLinesHourly(string $date, int|string $shift): void
    {
        $base = self::allLinesHourly($date, $shift);
        Cache::increment("{$base}:ver");
    }
}

