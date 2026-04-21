<?php

namespace App\Containers\AppSection\Production\Support;

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
     * all-lines-hourly:{date}:{shift}
     * Used by: GetAllLinesHourlyController, ResyncHourlyRecordsController/Command
     */
    public static function allLinesHourly(string $date, int|string $shift): string
    {
        return "all-lines-hourly:{$date}:{$shift}";
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
}
