<?php

namespace App\Containers\AppSection\Production\Support;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Compute per-department summary for production dashboards.
 *
 * Shared by:
 *   - GetDeptDetailController   (single department view)
 *   - GetAllLinesHourlyController (all-lines overview)
 */
final class DepartmentSummary
{
    /**
     * Build the summary array for a department's hourly records.
     *
     * @param Collection   $records     HourlyRecord collection (ordered by hour_index)
     * @param Department   $dept        The department model
     * @param ShiftDetail|null $shiftDetail Shift detail (nullable for safety)
     */
    public static function build(Collection $records, Department $dept, ?ShiftDetail $shiftDetail, ?CarbonImmutable $shiftDate = null, ?Carbon $shiftEndAt = null): array
    {
        $isPerMachineDtg  = $dept->productivity_type?->isPerMachineDtg() ?? false;
        $isPerMachineDtf  = $dept->productivity_type?->isPerMachineDtf() ?? false;

        // Always use shift_detail snapshot KPI for historical accuracy
        $kpiPerHour = $shiftDetail?->kpi_per_hour ?? ($dept->kpi_per_hour ?? 0);
        $defaultHeadcount = $shiftDetail?->headcount ?? 0;
        // Target multiplier: DTF → machine_count, per_person → headcount
        $defaultTargetMultiplier = $isPerMachineDtf ? ($shiftDetail?->machine_count ?? 0) : $defaultHeadcount;
        $dayStartInventory = $shiftDetail?->day_start_inventory ?? 0;

        $completedRecords = $records->whereNotNull('actual');

        // Effective targets via TargetEstimator
        $effectiveTargets = $records->map(fn ($r) => TargetEstimator::effective(
            $r->target,
            $kpiPerHour,
            $r->kpi_percent ?? 100,
            $isPerMachineDtg,
            $isPerMachineDtf
                ? ($r->machine_count ?? $defaultTargetMultiplier)
                : ($r->staff_required ?? $defaultHeadcount),
        ));

        $totalTarget    = $effectiveTargets->sum();
        $totalCompleted = $completedRecords->sum('actual');
        $remaining      = max(0, $dayStartInventory - $totalCompleted);

        // Target remaining = (active block gap) + Σ(pending targets)
        // Past shifts → all slots completed → target_remaining = 0
        $isPastShift = ($shiftDate && $shiftDate->lt(today()))
            || ($shiftEndAt && now()->gte($shiftEndAt));
        $isToday = $shiftDate && $shiftDate->eq(today());
        $targetRemaining = 0;
        foreach ($records as $i => $r) {
            $effectiveTarget = $effectiveTargets[$i];
            $status = self::resolveSlotStatus($r, $isPastShift, $isToday);
            if ($status === 'active') {
                $targetRemaining += max(0, $effectiveTarget - ($r->actual ?? 0));
            } elseif ($status === 'pending') {
                $targetRemaining += $effectiveTarget;
            }
        }

        $endingInventory = max(0, $dayStartInventory - $totalCompleted - $targetRemaining);

        // Efficiency: compute dynamically from actual / effectiveTarget
        $efficiencyValues = $records->map(function ($r, $i) use ($effectiveTargets) {
            $et = $effectiveTargets[$i] ?? 0;
            return ($r->actual !== null && $r->actual > 0 && $et > 0)
                ? round(($r->actual / $et) * 100, 1)
                : 0;
        })->filter(fn ($e) => $e > 0);

        $efficiency = $efficiencyValues->isNotEmpty()
            ? round($efficiencyValues->avg(), 2)
            : 0;

        // Efficiency at current moment: Σactual / Σtarget for slots from start up to now (completed + active)
        $currentActual = 0;
        $currentTarget = 0;
        foreach ($records as $i => $r) {
            $status = self::resolveSlotStatus($r, $isPastShift, $isToday);
            if ($status === 'completed' || $status === 'active') {
                $currentActual += $r->actual ?? 0;
                $currentTarget += $effectiveTargets[$i] ?? 0;
            }
        }
        $efficiencyCurrent = $currentTarget > 0
            ? round(($currentActual / $currentTarget) * 100, 2)
            : 0;

        // ── Estimated end time: first slot where department runs out of work ──
        $lastRecord = $records->last();
        $fallbackMultiplier = 0;
        if ($lastRecord) {
            $fallbackMultiplier = $isPerMachineDtf
                ? ($lastRecord->machine_count ?? $defaultTargetMultiplier)
                : ($lastRecord->staff ?? $defaultHeadcount);
        }
        if ($fallbackMultiplier <= 0) {
            $fallbackMultiplier = 1;
        }

        $fallbackCapacityPerHour = TargetEstimator::estimate(
            $kpiPerHour,
            100,
            $isPerMachineDtg,
            $fallbackMultiplier
        );
        [$estimatedEndTime, $outOfWorkAt] = self::computeEstimatedEndTime($records, $effectiveTargets, $fallbackCapacityPerHour, $endingInventory);

        // ── Per-machine/person overall efficiency across all hour slots ──
        $productivityEfficiency = self::computeProductivityEfficiency(
            $records, $dept, $kpiPerHour, $shiftDetail,
        );

        return [
            'total_target'        => $totalTarget,
            'total_completed'     => $totalCompleted,
            'completed'           => $totalCompleted,
            'target_remaining'    => $targetRemaining,
            'ending_inventory'    => $endingInventory,
            'remaining'           => $remaining,
            'day_start_inventory' => $dayStartInventory,
            'hotshot_total'       => $shiftDetail?->hotshot_total ?? 0,
            'hotshot_completed'   => $shiftDetail?->hotshot_completed ?? 0,
            'efficiency'          => $efficiency,
            'efficiency_current'  => $efficiencyCurrent,
            'actual_current'      => $currentActual,
            'target_current'      => $currentTarget,
            'error_rate'          => 0,
            'out_of_work_at'      => $outOfWorkAt,
            'estimated_end_time'  => $estimatedEndTime,
            'productivity_efficiency' => $productivityEfficiency,
        ];
    }

    /**
     * Compute overall efficiency per machine/person across all hour slots.
     *
     * Groups productivity_json items by identifier (machine, username, or printed_by),
     * sums their values and kpi_per_hour for each slot, then computes:
     *   efficiency = Σ value / Σ kpi_per_hour × 100
     *
     * @param Collection       $records     HourlyRecord collection
     * @param Department       $dept        The department model
     * @param float            $kpiPerHour  Department/shift-level KPI per hour (flat for non-DTG)
     * @param ShiftDetail|null $shiftDetail For DTG: contains machines with per-machine KPI
     * @return array<int, array{name: string, total_value: int, total_kpi: float, efficiency: float}>
     */
    public static function computeProductivityEfficiency(
        Collection $records,
        Department $dept,
        float $kpiPerHour,
        ?ShiftDetail $shiftDetail,
    ): array {
        $isPerMachineDtg = $dept->productivity_type?->isPerMachineDtg() ?? false;

        // Build machine KPI map for DTG: [strtolower(code) => kpi_per_hour]
        $machineKpiMap = null;
        if ($isPerMachineDtg && $shiftDetail) {
            $sdMachines = $shiftDetail->relationLoaded('machines')
                ? $shiftDetail->machines
                : $shiftDetail->machines()->with('machine')->get();

            $machineKpiMap = $sdMachines->mapWithKeys(fn ($sdm) => [
                strtolower($sdm->machine?->code ?? '') => $sdm->kpi_per_hour,
            ])->all();
        }

        // Aggregate: [identifier => ['total_value' => int, 'total_kpi' => float]]
        $aggregated = [];

        foreach ($records as $record) {
            $items = $record->productivity_json;
            if (empty($items)) {
                continue;
            }

            foreach ($items as $item) {
                // Determine the identifier key (machine, username, or printed_by)
                $name = $item['machine'] ?? $item['username'] ?? $item['printed_by'] ?? null;
                if ($name === null) {
                    continue;
                }

                $value = (int) ($item['value'] ?? 0);

                // Determine effective KPI for this item
                $effectiveKpi = $kpiPerHour;
                if ($machineKpiMap !== null) {
                    $machineKey = strtolower($item['printed_by'] ?? $item['machine'] ?? '');
                    $effectiveKpi = $machineKpiMap[$machineKey] ?? 0;
                }

                if (!isset($aggregated[$name])) {
                    $aggregated[$name] = ['total_value' => 0, 'total_kpi' => 0.0];
                }

                $aggregated[$name]['total_value'] += $value;
                $aggregated[$name]['total_kpi']   += $effectiveKpi;
            }
        }

        // Build result sorted by name
        $result = [];
        ksort($aggregated);

        foreach ($aggregated as $name => $data) {
            $result[] = [
                'name'        => $name,
                'total_value' => $data['total_value'],
                'total_kpi'   => $data['total_kpi'],
                'efficiency'  => $data['total_kpi'] > 0
                    ? round(($data['total_value'] / $data['total_kpi']) * 100, 1)
                    : 0,
            ];
        }

        return $result;
    }

    /**
     * Compute estimated end time from hourly records and their effective targets.
     *
     * Finds the first slot where hour_start_inventory <= effectiveTarget
     * and calculates proportional finish time within that slot.
     *
     * Reusable by:
     *   - DepartmentSummary::build()          (API response)
     *   - SyncOrderInventoryTask              (order estimated_done)
     *
     * @return array{0: string|null, 1: string|null} [estimatedEndTime, outOfWorkAt]
     */
    public static function computeEstimatedEndTime(
        Collection $records,
        Collection $effectiveTargets,
        ?float $fallbackCapacityPerHour = null,
        ?int $endingInventory = null
    ): array {
        $outOfWorkAt = null;
        $estimatedEndTime = null;

        foreach ($records as $i => $r) {
            $et = $effectiveTargets[$i] ?? 0;
            if ($r->hour_start_inventory !== null && $et > 0 && $r->hour_start_inventory <= $et) {
                $outOfWorkAt = $r->hour_slot;
                // Proportional: inventory/target × kpi_minutes from slot start
                $slotMinutes = $r->kpi_minutes ?? 60;
                $ratio = $r->hour_start_inventory / $et;
                $minutes = (int) ceil($ratio * $slotMinutes);
                $startHour = (int) explode('h', $r->hour_slot)[0];
                // Handle overflow: minutes >= 60 → carry to next hour
                $totalMinutes = $startHour * 60 + $minutes;
                $estimatedEndTime = self::formatTotalMinutes($totalMinutes);
                break;
            }
        }

        // Fallback: no out-of-work slot → calculate specific projected completion time
        if ($estimatedEndTime === null && $records->isNotEmpty()) {
            $lastRecord = $records->last();
            $lastEffectiveTarget = $effectiveTargets->last() ?? 0;

            // Use endingInventory if provided, otherwise fallback to lastInventory - lastEffectiveTarget
            if ($endingInventory !== null) {
                $remainingInventory = $endingInventory;
            } else {
                $lastInventory = $lastRecord->hour_start_inventory ?? 0;
                $remainingInventory = max(0, $lastInventory - $lastEffectiveTarget);
            }

            $startHour = (int) explode('h', $lastRecord->hour_slot)[0];
            $slotMinutes = $lastRecord->kpi_minutes ?? 60;

            $extraMinutes = 0;
            if ($remainingInventory > 0) {
                $capacityPerHour = $lastEffectiveTarget > 0 ? $lastEffectiveTarget : ($fallbackCapacityPerHour ?? 0);
                if ($capacityPerHour > 0) {
                    $ratePerMinute = $capacityPerHour / $slotMinutes;
                    $extraMinutes = (int) ceil($remainingInventory / $ratePerMinute);
                }
            }

            $totalMinutes = $startHour * 60 + $slotMinutes + $extraMinutes;
            $estimatedEndTime = self::formatTotalMinutes($totalMinutes);
        }

        return [$estimatedEndTime, $outOfWorkAt];
    }

    /**
     * Format total minutes into HH:MM, wrapping hours around 24-hour limit and appending day offsets.
     */
    private static function formatTotalMinutes(int $totalMinutes): string
    {
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        if ($hours >= 24) {
            $days = intdiv($hours, 24);
            $hours = $hours % 24;
            return sprintf('%02d:%02d + %dd', $hours, $minutes, $days);
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Resolve slot status with real-time accuracy (same logic as HourlyRecordTransformer).
     *
     * Past shifts → 'completed'.
     * Today → compute from hour_slot + now() using integer hour comparison.
     * Otherwise → use DB status.
     */
    public static function resolveSlotStatus(mixed $record, bool $isPastShift, bool $isToday): string
    {
        if ($isPastShift) {
            return 'completed';
        }

        if ($isToday && $record->hour_slot) {
            $parts = explode('-', str_replace('h', '', $record->hour_slot));
            $startHour = (int) ($parts[0] ?? 0);
            $endHour   = (int) ($parts[1] ?? 0);
            $currentHour = (int) now()->format('G');

            if ($currentHour >= $endHour) {
                return 'completed';
            }
            if ($currentHour >= $startHour) {
                return 'active';
            }
            return 'pending';
        }

        return $record->status;
    }
}
