<?php

namespace App\Containers\AppSection\Shift\Traits;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\ShiftDetail;

/**
 * Shared target computation logic for hourly records.
 *
 * Used by GenerateHourlyRecordsTask, SyncHourlyRecordsTask, UpdateHourlyStaffTask.
 *
 * Per-person:  target = department.kpi_per_hour × multiplier (headcount or staff)
 * Per-machine: target = shift_detail.kpi_per_hour (Σ machine KPIs, ignores multiplier)
 */
trait ComputesHourlyTarget
{
    /**
     * Compute hourly target based on productivity type.
     *
     * @param Department|null $dept       The department (with productivity_type)
     * @param ShiftDetail     $detail     The shift detail (with kpi_per_hour for per_machine)
     * @param int             $multiplier headcount or staff count (only used for per_person)
     */
    protected function computeTarget(?Department $dept, ShiftDetail $detail, int $multiplier): int
    {
        $isPerMachine = $dept?->productivity_type === ProductivityType::PerMachine;

        if ($isPerMachine) {
            // Per-machine: target = Σ(selected machine KPIs), stored in shift_detail.kpi_per_hour
            return $detail->kpi_per_hour ?? 0;
        }

        // Per-person: target = department.kpi_per_hour × multiplier
        $kpiPerHour = $dept?->kpi_per_hour ?? 0;

        return (int) round($kpiPerHour * $multiplier);
    }
}
