<?php

namespace App\Containers\AppSection\Shift\Traits;

use App\Containers\AppSection\Shift\Models\ShiftDetail;
use Illuminate\Support\Carbon;

/**
 * Shared KPI hours computation logic.
 *
 * kpi_hours = slot_duration − break_overlap
 *
 * Used by GenerateHourlyRecordsTask and SyncHourlyRecordsTask.
 */
trait ComputesKpiHours
{
    /**
     * Compute effective KPI hours for a slot by subtracting break overlaps.
     *
     * @param  Carbon                                    $slotStart
     * @param  Carbon                                    $slotEnd
     * @param  array<array{start: Carbon, end: Carbon}>  $breaks
     */
    protected function computeKpiHours(Carbon $slotStart, Carbon $slotEnd, array $breaks): float
    {
        $overlapMinutes = 0;

        foreach ($breaks as $break) {
            $overlapStart = $slotStart->copy()->max($break['start']);
            $overlapEnd   = $slotEnd->copy()->min($break['end']);

            if ($overlapStart->lt($overlapEnd)) {
                $overlapMinutes += $overlapStart->diffInMinutes($overlapEnd);
            }
        }

        $slotMinutes = $slotStart->diffInMinutes($slotEnd);
        $kpiMinutes  = max(0, $slotMinutes - $overlapMinutes);

        return round($kpiMinutes / 60, 2);
    }

    /**
     * Collect all break periods from a ShiftDetail as Carbon time ranges.
     *
     * @return array<array{start: Carbon, end: Carbon}>
     */
    protected function collectBreaks(ShiftDetail $detail): array
    {
        $breaks = [];

        $breakFields = [
            ['break1_start',      'break1_minutes'],
            ['meal_break_start',  'meal_break_minutes'],
            ['break2_start',      'break2_minutes'],
            ['break3_start',      'break3_minutes'],
        ];

        foreach ($breakFields as [$startField, $minutesField]) {
            $startTime = $detail->{$startField};
            $minutes   = $detail->{$minutesField} ?? 0;

            if ($startTime && $minutes > 0) {
                $breakStart = Carbon::createFromFormat('H:i:s', $startTime);
                $breaks[] = [
                    'start' => $breakStart,
                    'end'   => $breakStart->copy()->addMinutes($minutes),
                ];
            }
        }

        return $breaks;
    }
}
