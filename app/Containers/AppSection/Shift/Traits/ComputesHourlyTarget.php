<?php

namespace App\Containers\AppSection\Shift\Traits;


use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\ShiftDetail;

/**
 * Shared target computation logic for hourly records.
 *
 * Used by GenerateHourlyRecordsTask, SyncHourlyRecordsTask, UpdateHourlyStaffTask.
 *
 * Per-person:      target = department.kpi_per_hour × multiplier (headcount or staff)
 * Per-machine DTG: target = shift_detail.kpi_per_hour (Σ machine KPIs, ignores multiplier)
 * Per-machine DTF: target = department.kpi_per_hour × multiplier (machine_count)
 */
trait ComputesHourlyTarget
{
    /**
     * Compute hourly target based on productivity type.
     *
     * @param Department|null $dept       The department (with productivity_type)
     * @param ShiftDetail     $detail     The shift detail (with kpi_per_hour for per_machine_dtg)
     * @param int             $multiplier headcount (per_person), machine_count (DTF), ignored (DTG)
     */
    protected function computeTarget(?Department $dept, ShiftDetail $detail, int $multiplier): int
    {
        // DTG: target = Σ(selected machine KPIs), stored in shift_detail.kpi_per_hour
        if ($dept?->productivity_type?->isPerMachineDtg()) {
            return $detail->kpi_per_hour ?? 0;
        }

        // Per-person: target = department.kpi_per_hour × headcount
        // DTF:        target = department.kpi_per_hour × machine_count
        $kpiPerHour = $dept?->kpi_per_hour ?? 0;

        return (int) round($kpiPerHour * $multiplier);
    }
}
