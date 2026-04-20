<?php

namespace App\Containers\AppSection\Shift\Traits;

use App\Containers\AppSection\Shift\Models\ShiftDetail;
use Illuminate\Support\Carbon;

/**
 * Shared KPI hours and slot computation logic.
 *
 * kpi_hours = slot_duration − break_overlap
 *
 * Used by GenerateHourlyRecordsTask and SyncHourlyRecordsTask.
 */
trait ComputesKpiHours
{
    /**
     * Compute effective KPI data for a slot by subtracting break overlaps.
     *
     * Returns:
     *   - minutes: int   — exact integer minutes (no rounding loss, single source of truth)
     *   - percent: float — kpi_minutes / 60 * 100, rounded to 2dp
     *   - hours:   float — kpi_minutes / 60, rounded to 2dp (backward-compat)
     *
     * @param  Carbon                                    $slotStart
     * @param  Carbon                                    $slotEnd
     * @param  array<array{start: Carbon, end: Carbon}>  $breaks
     * @return array{minutes: int, percent: float, hours: float}
     */
    protected function computeKpiHoursData(Carbon $slotStart, Carbon $slotEnd, array $breaks): array
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

        return [
            'minutes' => $kpiMinutes,
            'percent' => round($kpiMinutes / 60 * 100, 2),
            'hours'   => round($kpiMinutes / 60, 2),
        ];
    }

    /**
     * Compute effective KPI hours for a slot (backward-compatible wrapper).
     *
     * Prefer computeKpiHoursData() for new code to get all three values at once.
     *
     * @param  Carbon                                    $slotStart
     * @param  Carbon                                    $slotEnd
     * @param  array<array{start: Carbon, end: Carbon}>  $breaks
     */
    protected function computeKpiHours(Carbon $slotStart, Carbon $slotEnd, array $breaks): float
    {
        return $this->computeKpiHoursData($slotStart, $slotEnd, $breaks)['hours'];
    }


    /**
     * Collect all break periods from a ShiftDetail as Carbon time ranges.
     *
     * Optionally includes prep_minutes as a synthetic break at the very start
     * of the shift (anchored to $shiftStartTime) so that the first hourly slot's
     * kpi_hours is automatically reduced by the preparation time.
     *
     * @param  string|null  $shiftStartTime  e.g. '06:30:00' — the department start_time.
     *                                       When provided, prep_minutes is inserted as
     *                                       the first entry in the returned array.
     * @return array<array{start: Carbon, end: Carbon}>
     */
    protected function collectBreaks(ShiftDetail $detail, ?string $shiftStartTime = null): array
    {
        $breaks = [];

        // ── Prep time as a synthetic break at shift start ──
        $prepMinutes = (int) ($detail->prep_minutes ?? 0);
        $startTime   = $shiftStartTime ?? $detail->start_time;

        if ($prepMinutes > 0 && $startTime) {
            $format     = substr_count($startTime, ':') === 2 ? 'H:i:s' : 'H:i';
            $prepStart  = Carbon::createFromFormat($format, $startTime);
            $breaks[] = [
                'start' => $prepStart,
                'end'   => $prepStart->copy()->addMinutes($prepMinutes),
            ];
        }

        // ── Regular breaks ──
        $breakFields = [
            ['break1_start',      'break1_minutes'],
            ['meal_break_start',  'meal_break_minutes'],
            ['break2_start',      'break2_minutes'],
            ['break3_start',      'break3_minutes'],
        ];

        foreach ($breakFields as [$startField, $minutesField]) {
            $breakTime = $detail->{$startField};
            $minutes   = $detail->{$minutesField} ?? 0;

            if ($breakTime && $minutes > 0) {
                $format = substr_count($breakTime, ':') === 2 ? 'H:i:s' : 'H:i';
                $breakStart = Carbon::createFromFormat($format, $breakTime);
                $breaks[] = [
                    'start' => $breakStart,
                    'end'   => $breakStart->copy()->addMinutes($minutes),
                ];
            }
        }

        return $breaks;
    }

    /**
     * Build full-hour-aligned slots between start and end times.
     *
     * Returns array of slots, each with:
     * - label: "6h-7h" format
     * - fraction: 0.0–1.0 (portion of a full hour)
     * - start: Carbon (actual slot start time)
     * - end: Carbon (actual slot end time)
     *
     * @return array<array{label: string, fraction: float, start: Carbon, end: Carbon}>
     */
    protected function buildAlignedSlots(Carbon $start, Carbon $end): array
    {
        $slots = [];

        $firstFullHour = $start->copy()->startOfHour();
        if ($firstFullHour->lt($start)) {
            $firstFullHour->addHour();
        }

        $lastFullHour = $end->copy()->startOfHour();

        // Partial first slot (if start is not on the hour)
        if ($start->minute > 0 && $firstFullHour->lte($end)) {
            $slotEnd = $firstFullHour->copy()->min($end);
            $minutes = $start->diffInMinutes($slotEnd);

            $slots[] = [
                'label'    => $start->format('G') . 'h-' . $slotEnd->format('G') . 'h',
                'fraction' => round($minutes / 60, 2),
                'start'    => $start->copy(),
                'end'      => $slotEnd->copy(),
            ];
        }

        // Full-hour slots
        $cursor = $firstFullHour->copy();
        while ($cursor->lt($lastFullHour)) {
            $slotEnd = $cursor->copy()->addHour();

            $slots[] = [
                'label'    => $cursor->format('G') . 'h-' . $slotEnd->format('G') . 'h',
                'fraction' => 1.0,
                'start'    => $cursor->copy(),
                'end'      => $slotEnd->copy(),
            ];

            $cursor->addHour();
        }

        // Partial last slot (if end is not on the hour)
        if ($end->minute > 0 && $lastFullHour->gte($firstFullHour)) {
            $minutes = $lastFullHour->diffInMinutes($end);

            $slots[] = [
                'label'    => $lastFullHour->format('G') . 'h-' . $end->copy()->startOfHour()->addHour()->format('G') . 'h',
                'fraction' => round($minutes / 60, 2),
                'start'    => $lastFullHour->copy(),
                'end'      => $end->copy(),
            ];
        }

        return $slots;
    }
}
