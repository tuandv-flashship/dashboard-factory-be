<?php

namespace App\Containers\AppSection\Production\Jobs;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Production\Support\TargetEstimator;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Aggregate hourly records from child departments into a parent department.
 *
 * Dispatched AFTER all child SyncDepartmentHourlyJobs complete.
 *
 * From children: actual, staff, productivity_json (FPlatform data)
 * From parent's own data: hour_start_inventory, target, efficiency
 *   (same cascade logic as independent departments)
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

        // Fetch parent hourly_records ordered by hour_index
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

        // ── Aggregate shift_detail fields from children FIRST ──
        // (so parent's day_start_inventory is up-to-date before cascade)
        $this->aggregateShiftDetail($shift, $parent, $childIds);

        // ── Load parent's shift_detail for independent target/inventory calc ──
        $parentDetail = ShiftDetail::where('shift_id', $shift->id)
            ->where('department_id', $parent->id)
            ->first();

        $kpiPerHour     = $parentDetail->kpi_per_hour ?? ($parent->kpi_per_hour ?? 0);
        $defaultHeadcount = $parentDetail->headcount ?? 0;
        $isPerMachineDtg = $parent->productivity_type?->isPerMachineDtg() ?? false;
        $isPerMachineDtf = $parent->productivity_type?->isPerMachineDtf() ?? false;

        // Start inventory cascade from parent's own day_start_inventory
        $currentInv = $parentDetail->day_start_inventory ?? 0;

        // Parse shift timing for slot classification
        $now       = now();
        $shiftDate = $shift->date->toDateString();

        $updated = 0;

        foreach ($parentRecords as $hourIndex => $parentRecord) {
            $childGroup = $childRecords->get($hourIndex, collect());

            // ── Aggregate FPlatform data from children ──
            $hasAnyActual = $childGroup->isNotEmpty() && $childGroup->contains(fn ($r) => $r->actual !== null);
            $totalActual  = $hasAnyActual ? (int) $childGroup->sum('actual') : null;

            $hasAnyStaff = $childGroup->isNotEmpty() && $childGroup->contains(fn ($r) => $r->staff !== null);
            $totalStaff  = $hasAnyStaff ? (int) $childGroup->sum('staff') : null;

            // Merge productivity_json: concat all children items
            $mergedProductivity = $childGroup->isNotEmpty()
                ? $childGroup->pluck('productivity_json')->filter()->flatten(1)->values()->toArray()
                : [];

            // ── Parent's own target (same as TargetEstimator in other departments) ──
            $effectiveTarget = TargetEstimator::effective(
                $parentRecord->target,
                $kpiPerHour,
                $parentRecord->kpi_percent ?? 100,
                $isPerMachineDtg,
                $isPerMachineDtf
                    ? ($parentRecord->machine_count ?? $parentDetail->machine_count ?? 0)
                    : ($parentRecord->staff_required ?? $defaultHeadcount),
            );

            // ── Parent's own inventory cascade ──
            $hourStartInventory = max(0, $currentInv);

            // ── Slot classification (same logic as SyncDepartmentHourlyJob) ──
            [$slotStart, $slotEnd] = $this->parseHourSlot($parentRecord->hour_slot, $shiftDate);
            $isPassedSlot  = $now >= $slotEnd;
            $isFutureSlot  = $now < $slotStart;

            // ── Efficiency (parent's own target vs aggregated actual) ──
            $efficiency = ($effectiveTarget > 0 && $totalActual !== null && $totalActual > 0)
                ? round(($totalActual / $effectiveTarget) * 100, 1)
                : 0;

            $updates = [
                'actual'               => $totalActual,
                'staff'                => $totalStaff,
                'hour_start_inventory' => $hourStartInventory,
                'efficiency'           => $efficiency,
                'productivity_json'    => !empty($mergedProductivity) ? $mergedProductivity : null,
            ];

            $parentRecord->update($updates);
            $updated++;

            // ── Advance inventory: passed → subtract actual, active/future → subtract target ──
            if ($isPassedSlot) {
                $currentInv = max(0, $currentInv - ($totalActual ?? 0));
            } else {
                $currentInv = max(0, $currentInv - $effectiveTarget);
            }
        }

        if ($updated > 0) {
            Log::info("[AggregateParentHourly] Aggregated {$updated} records for parent dept {$parent->code}.");
            ProductionCacheKeys::flushForDepartment($shift, $parent);
        }
    }

    /**
     * Parse hour_slot string (e.g. "9h-10h") into Carbon start/end.
     */
    private function parseHourSlot(string $hourSlot, string $shiftDate): array
    {
        $parts = explode('-', str_replace('h', '', $hourSlot));
        $startHour = (int) $parts[0];
        $endHour   = (int) $parts[1];

        return [
            Carbon::createFromFormat('Y-m-d H:i:s', "{$shiftDate} {$startHour}:00:00"),
            Carbon::createFromFormat('Y-m-d H:i:s', "{$shiftDate} {$endHour}:00:00"),
        ];
    }

    /**
     * Sum day_start_inventory, hotshot_total, hotshot_completed
     * from children's shift_details into the parent's shift_detail.
     *
     * Runs BEFORE the hourly cascade so parent's day_start_inventory
     * is up-to-date for inventory projection.
     */
    private function aggregateShiftDetail(Shift $shift, Department $parent, array $childIds): void
    {
        $parentDetail = ShiftDetail::where('shift_id', $shift->id)
            ->where('department_id', $parent->id)
            ->first();

        if (!$parentDetail) {
            return;
        }

        $childDetails = ShiftDetail::where('shift_id', $shift->id)
            ->whereIn('department_id', $childIds)
            ->get();

        if ($childDetails->isEmpty()) {
            return;
        }

        $totalDayStartInventory = $childDetails->sum('day_start_inventory');
        $totalHotshotTotal      = $childDetails->sum('hotshot_total');
        $totalHotshotCompleted  = $childDetails->sum('hotshot_completed');

        $changed = $parentDetail->day_start_inventory !== $totalDayStartInventory
            || $parentDetail->hotshot_total !== $totalHotshotTotal
            || $parentDetail->hotshot_completed !== $totalHotshotCompleted;

        if ($changed) {
            $parentDetail->update([
                'day_start_inventory' => $totalDayStartInventory,
                'hotshot_total'       => $totalHotshotTotal,
                'hotshot_completed'   => $totalHotshotCompleted,
            ]);

            Log::info("[AggregateParentHourly] ShiftDetail aggregated for parent {$parent->code}.", [
                'day_start_inventory' => $totalDayStartInventory,
                'hotshot_total'       => $totalHotshotTotal,
                'hotshot_completed'   => $totalHotshotCompleted,
            ]);
        }
    }
}
