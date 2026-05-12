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
        $kpiPerHour       = $isPerMachineDtg
            ? ($shiftDetail?->kpi_per_hour ?? 0)
            : ($dept->kpi_per_hour ?? 0);
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
        $targetRemaining = 0;
        foreach ($records as $i => $r) {
            $effectiveTarget = $effectiveTargets[$i];
            $status = $isPastShift ? 'completed' : $r->status;
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
            'error_rate'          => 0,
        ];
    }
}
