<?php

namespace App\Containers\AppSection\Production\Jobs;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Shift\Models\Shift;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Aggregate hourly records from child departments into a parent department.
 *
 * Dispatched AFTER all child SyncDepartmentHourlyJobs complete.
 * Sums ALL numeric data fields from children into the parent's hourly_records.
 *
 * Used for the Pick parent department (PD factory):
 *   Pick (parent) = Pick DTF (child) + Pick DTG (child)
 */
final class AggregateParentHourlyJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $shiftId,
        private readonly int $parentDepartmentId,
    ) {
    }

    public function handle(): void
    {
        $parent = Department::with('children')->find($this->parentDepartmentId);
        if (!$parent || $parent->children->isEmpty()) {
            return;
        }

        $shift = Shift::find($this->shiftId);
        if (!$shift) {
            return;
        }

        $childIds = $parent->children->pluck('id')->toArray();

        // Fetch all children hourly_records, grouped by hour_index
        $childRecords = HourlyRecord::where('shift_id', $this->shiftId)
            ->whereIn('department_id', $childIds)
            ->get()
            ->groupBy('hour_index');

        // Fetch parent hourly_records
        $parentRecords = HourlyRecord::where('shift_id', $this->shiftId)
            ->where('department_id', $this->parentDepartmentId)
            ->orderBy('hour_index')
            ->get()
            ->keyBy('hour_index');

        if ($parentRecords->isEmpty()) {
            Log::info('[AggregateParentHourly] No parent records found', [
                'shift_id'      => $this->shiftId,
                'department_id' => $this->parentDepartmentId,
            ]);
            return;
        }

        $updated = 0;

        foreach ($parentRecords as $hourIndex => $parentRecord) {
            $childGroup = $childRecords->get($hourIndex, collect());

            if ($childGroup->isEmpty()) {
                continue;
            }

            // ── Aggregate ALL data fields from children ──

            // Actual: sum of children actuals (null if ALL children are null)
            $hasAnyActual = $childGroup->contains(fn ($r) => $r->actual !== null);
            $totalActual = $hasAnyActual ? $childGroup->sum('actual') : null;

            // Staff: sum of children staff
            $hasAnyStaff = $childGroup->contains(fn ($r) => $r->staff !== null);
            $totalStaff = $hasAnyStaff ? $childGroup->sum('staff') : null;

            // hour_start_inventory: sum of children
            $totalHourStartInventory = $childGroup->sum('hour_start_inventory');

            // Status: determined from children statuses
            $statuses = $childGroup->pluck('status')->unique();
            if ($statuses->every(fn ($s) => $s === HourlyRecordStatus::Completed->value)) {
                $status = HourlyRecordStatus::Completed->value;
            } elseif ($statuses->contains(HourlyRecordStatus::Active->value)) {
                $status = HourlyRecordStatus::Active->value;
            } else {
                $status = HourlyRecordStatus::Pending->value;
            }

            // Target: parent follows standard logic (TargetEstimator / staff confirmation)
            // — NOT aggregated from children. Don't touch parent's target here.
            $effectiveTarget = $parentRecord->target ?? 0;

            $efficiency = ($effectiveTarget > 0 && $totalActual !== null && $totalActual > 0)
                ? round(($totalActual / $effectiveTarget) * 100, 1)
                : 0;

            // Merge productivity_json: concat all children items
            $mergedProductivity = $childGroup
                ->pluck('productivity_json')
                ->filter()
                ->flatten(1)
                ->values()
                ->toArray();

            $updates = [
                'actual'               => $totalActual,
                'staff'                => $totalStaff,
                'hour_start_inventory' => $totalHourStartInventory,
                'efficiency'           => $efficiency,
                'status'               => $status,
                'productivity_json'    => !empty($mergedProductivity) ? $mergedProductivity : null,
            ];

            $parentRecord->update($updates);
            $updated++;
        }

        if ($updated > 0) {
            Log::info("[AggregateParentHourly] Aggregated {$updated} records for parent dept {$parent->code}.");
            ProductionCacheKeys::flushForDepartment($shift, $parent);
        }
    }
}
