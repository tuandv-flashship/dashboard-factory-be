<?php

namespace App\Containers\AppSection\Production\Support;

/**
 * Centralized target calculation for hourly records.
 *
 * Single source of truth — used by:
 *   - HourlyRecordTransformer  (API response)
 *   - GetDeptDetailController  (summary computation)
 *   - UpdateHourlyStaffTask    (cascade inventory)
 *   - SyncDepartmentHourlyJob  (FPlatform sync)
 *
 * Formula:
 *   per_person:  kpi_per_hour × kpi_percent / 100 × staff_required
 *   per_machine: kpi_per_hour × kpi_percent / 100
 *                (kpi_per_hour from shiftDetail is already the sum of all machines)
 */
final class TargetEstimator
{
    /**
     * Return the effective target for an hourly record slot.
     *
     * Uses the manual target when explicitly set (> 0).
     * Otherwise, falls back to the estimated target.
     */
    public static function effective(
        ?int $manualTarget,
        float $kpiPerHour,
        float $kpiPercent,
        bool $isPerMachine,
        int $staffRequired,
    ): int {
        if ($manualTarget !== null && $manualTarget > 0) {
            return $manualTarget;
        }

        return self::estimate($kpiPerHour, $kpiPercent, $isPerMachine, $staffRequired);
    }

    /**
     * Estimate target (ignoring any manual override).
     */
    public static function estimate(
        float $kpiPerHour,
        float $kpiPercent,
        bool $isPerMachine,
        int $staffRequired,
    ): int {
        $base = (int) round($kpiPerHour * $kpiPercent / 100);

        return $isPerMachine ? $base : $base * $staffRequired;
    }
}
