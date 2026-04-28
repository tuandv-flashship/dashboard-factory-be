<?php

namespace App\Containers\AppSection\Production\Support;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
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
    public static function build(Collection $records, Department $dept, ?ShiftDetail $shiftDetail): array
    {
        $isPerMachine     = $dept->productivity_type === ProductivityType::PerMachine;
        $kpiPerHour       = $isPerMachine
            ? ($shiftDetail?->kpi_per_hour ?? 0)
            : ($dept->kpi_per_hour ?? 0);
        $defaultHeadcount = $shiftDetail?->headcount ?? 0;
        $dayStartInventory = $shiftDetail?->day_start_inventory ?? 0;

        $completedRecords = $records->whereNotNull('actual');

        // Effective targets via TargetEstimator
        $effectiveTargets = $records->map(fn ($r) => TargetEstimator::effective(
            $r->target,
            $kpiPerHour,
            $r->kpi_percent ?? 100,
            $isPerMachine,
            $r->staff_required ?? $defaultHeadcount,
        ));

        $totalTarget    = $effectiveTargets->sum();
        $totalCompleted = $completedRecords->sum('actual');
        $remaining      = max(0, $dayStartInventory - $totalCompleted);

        // Target remaining = (active block gap) + Σ(pending targets)
        $targetRemaining = 0;
        foreach ($records as $i => $r) {
            $effectiveTarget = $effectiveTargets[$i];
            if ($r->status === 'active') {
                $targetRemaining += max(0, $effectiveTarget - ($r->actual ?? 0));
            } elseif ($r->status === 'pending') {
                $targetRemaining += $effectiveTarget;
            }
        }

        $endingInventory = max(0, $dayStartInventory - $totalCompleted - $targetRemaining);

        // Efficiency: average of completed records with efficiency > 0
        $withEfficiency = $completedRecords->where('efficiency', '>', 0);
        $efficiency = $withEfficiency->isNotEmpty()
            ? round($withEfficiency->avg('efficiency'), 2)
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
