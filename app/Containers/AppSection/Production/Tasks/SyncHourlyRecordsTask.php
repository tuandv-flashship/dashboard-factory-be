<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\FplatformData\Tasks\GetAllTeamsInventoryTask;
use App\Containers\AppSection\FplatformData\Actions\GetHourlyMetricsAction;
use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Traits\ComputesKpiHours;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Sync hourly_records with real-time data from FPlatform.
 *
 * For each department's hourly records:
 * - Pending slots whose time has arrived → activate + fetch data
 * - Active current slot → refresh data from FPlatform
 * - Active past slots → fetch data + complete (with grace period)
 * - Already completed → recalculate staff_required + target + efficiency (no API call)
 *
 * Called by SyncHourlyRecordsJob every N minutes.
 */
final class SyncHourlyRecordsTask extends ParentTask
{
    use ComputesKpiHours;

    private const DEPT_TEAM_MAP = [
        'print'     => Team::Print,
        'cut'       => Team::Cut,
        'pick'      => Team::Pick,
        'mockup'    => Team::Mockup,
        'pack_ship' => Team::PackShip,
        'pick_dtg'  => Team::PickDtg,
        'dtg_print' => Team::DtgPrint,
    ];

    /** Max hours to retry fetching data for a past slot before force-completing. */
    private const COMPLETE_GRACE_HOURS = 2;

    public function __construct(
        private readonly GetHourlyMetricsAction $hourlyMetricsAction,
        private readonly GetAllTeamsInventoryTask $allTeamsInventoryTask,
    ) {
    }

    /**
     * @return array{synced: int, shift: Shift|null, message: string}
     */
    public function run(?string $date = null, ?int $shiftNumber = null): array
    {
        $shift = ($date || $shiftNumber)
            ? Shift::resolve($date, $shiftNumber)
            : Shift::current();

        if (!$shift) {
            $msg = '[SyncHourlyRecords] No shift found — skipped.';
            Log::info($msg, ['date' => $date ?? now()->toDateString(), 'shift' => $shiftNumber]);

            return ['synced' => 0, 'shift' => null, 'message' => 'No shift found.'];
        }

        $shiftDate = $shift->date->toDateString();

        $shiftDetails = ShiftDetail::with('department')
            ->where('shift_id', $shift->id)
            ->get();

        if ($shiftDetails->isEmpty()) {
            return ['synced' => 0, 'shift' => $shift, 'message' => 'No shift details found.'];
        }

        $allInventory = $this->allTeamsInventoryTask->run($shiftDate);

        $synced = 0;

        foreach ($shiftDetails as $detail) {
            $dept = $detail->department;
            if (!$dept) {
                continue;
            }

            $team = self::DEPT_TEAM_MAP[$dept->code] ?? null;
            if (!$team) {
                continue;
            }

            try {
                $synced += $this->syncDepartment($shift, $detail, $dept, $team, $allInventory);
            } catch (\Throwable $e) {
                Log::warning('[SyncHourlyRecords] Failed for department', [
                    'department' => $dept->code,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $msg = "Synced {$synced} records for {$shiftDate} shift {$shift->shift_number}.";

        if ($synced > 0) {
            Log::info("[SyncHourlyRecords] {$msg}");
        }

        return ['synced' => $synced, 'shift' => $shift, 'message' => $msg];
    }

    /**
     * Sync all hourly records for a single department.
     *
     * Pass 1: Recalculate kpi_hours for all records.
     * Pass 2: Sync data from FPlatform for active/past slots, recalculate metrics for completed.
     *
     * @return int Number of records updated
     */
    private function syncDepartment(
        Shift $shift,
        ShiftDetail $detail,
        Department $dept,
        Team $team,
        array $allInventory,
    ): int {
        $now = now();
        $shiftDate = $shift->date->toDateString();

        $deptStart = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $shiftDate . ' ' . $detail->start_time
        );

        $records = HourlyRecord::where('shift_id', $shift->id)
            ->where('department_id', $dept->id)
            ->orderBy('hour_index')
            ->get();

        $dayStartInventory = $this->refreshDayStartInventory($detail, $team, $allInventory);
        $breaks = $this->collectBreaks($detail);

        // Build slot map using shared trait method
        $deptWorkStart = Carbon::createFromFormat('H:i:s', $detail->start_time);
        $deptWorkEnd   = $deptWorkStart->copy()->addMinutes((int) ($detail->work_hours * 60));
        $alignedSlots  = $this->buildAlignedSlots($deptWorkStart, $deptWorkEnd);

        // Index by hour_index for O(1) lookup
        $slotMap = [];
        foreach ($alignedSlots as $i => $slot) {
            $slotMap[$i] = $slot;
        }

        // ── Pass 1: Recalculate all kpi_hours ──────────────
        foreach ($records as $record) {
            $slot = $slotMap[$record->hour_index] ?? null;

            $kpiHours = $slot
                ? $this->computeKpiHours($slot['start'], $slot['end'], $breaks)
                : $this->computeKpiHoursFromLabel($record->hour_slot, $shiftDate, $breaks);

            if ($record->kpi_hours != $kpiHours) {
                $record->update(['kpi_hours' => $kpiHours]);
                $record->kpi_hours = $kpiHours;
            }
        }

        // ── Pre-compute shared values (accurate after pass 1) ──
        $totalKpiHours = $records->sum('kpi_hours');
        $lastHourIndex = $records->max('hour_index');
        $isPerMachine  = $dept->productivity_type === ProductivityType::PerMachine;
        $kpiPerHour    = $isPerMachine ? ($detail->kpi_per_hour ?? 0) : ($dept->kpi_per_hour ?? 0);

        // ── Pass 2: Sync data ─────────────────────────────
        $updated = 0;

        foreach ($records as $record) {
            [$queryStart, $queryEnd] = $this->parseHourSlot($record->hour_slot, $shiftDate);

            $activationTime = $deptStart->gt($queryStart) ? $deptStart->copy() : $queryStart->copy();
            $actualSlot = $slotMap[$record->hour_index] ?? null;

            // Slot hasn't started yet → stay pending
            if ($now < $activationTime) {
                continue;
            }

            // Compute previous slot aggregates in one pass
            $pastActual   = 0;
            $pastKpiHours = 0.0;
            foreach ($records as $r) {
                if ($r->hour_index >= $record->hour_index) {
                    break; // Records are ordered by hour_index
                }
                $pastActual   += (int) $r->actual;
                $pastKpiHours += (float) $r->kpi_hours;
            }

            // ── Already completed → recalc metrics only (no API call) ──
            if ($record->status === HourlyRecordStatus::Completed->value) {
                $remainingKpiHours = $totalKpiHours - $pastKpiHours;
                $staffRequired = $this->computeStaffRequired($dept, $record->hour_start_inventory, $remainingKpiHours);

                $target = $this->computeTarget(
                    $staffRequired, $kpiPerHour, $record->kpi_hours,
                    $record->hour_start_inventory, $record->hour_index, $lastHourIndex, $record->target
                );

                $record->update([
                    'staff_required' => $staffRequired,
                    'target'         => $target,
                    'efficiency'     => $target > 0 && $record->actual > 0
                        ? round(($record->actual / $target) * 100, 1)
                        : 0,
                ]);

                $updated++;
                continue;
            }

            // ── Active / Pending slots: fetch from FPlatform ──
            $isCurrentSlot = $now >= $activationTime && $now < $queryEnd;
            $isPassedSlot = $now >= $queryEnd;

            if ($isCurrentSlot || $isPassedSlot) {
                $this->fetchAndUpdateRecord(
                    $record, $team, $dept, $detail,
                    $queryStart, $queryEnd, $dayStartInventory,
                    $pastActual, $pastKpiHours, $totalKpiHours,
                    $kpiPerHour, $lastHourIndex,
                    $breaks, $actualSlot,
                );

                if ($isPassedSlot) {
                    $record->refresh();
                    $graceExpired = $now->diffInHours($queryEnd) >= self::COMPLETE_GRACE_HOURS;

                    if ($record->actual > 0 || $graceExpired) {
                        $record->update(['status' => HourlyRecordStatus::Completed->value]);
                    }
                }

                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Fetch FPlatform data and update a single hourly record.
     *
     * Pre-computed aggregates are passed in to avoid duplicate collection scans.
     */
    private function fetchAndUpdateRecord(
        HourlyRecord $record,
        Team $team,
        Department $dept,
        ShiftDetail $detail,
        Carbon $slotStart,
        Carbon $slotEnd,
        int $dayStartInventory,
        int $pastActual,
        float $pastKpiHours,
        float $totalKpiHours,
        float $kpiPerHour,
        int $lastHourIndex,
        array $breaks,
        ?array $actualSlot = null,
    ): void {
        $startShift = $slotStart->format('Y-m-d H:i:s');
        $endShift = $slotEnd->format('Y-m-d H:i:s');

        // ── Fetch productivity (actual) ──────────────────────
        $productivityResult = $this->hourlyMetricsAction->run(
            $team,
            HourlyMetricType::Productivity,
            $startShift,
            $endShift,
        );

        $actual = $this->sumHourlyValues($productivityResult['hours']);

        // ── Fetch staff count ────────────────────────────────
        $staffResult = $this->hourlyMetricsAction->run(
            $team,
            HourlyMetricType::StaffCount,
            $startShift,
            $endShift,
        );

        $staff = $this->sumHourlyValues($staffResult['hours'], 'num_staff');

        // ── Calculate metrics using pre-computed aggregates ───
        $hourStartInventory = max(0, $dayStartInventory - $pastActual);
        $remainingKpiHours  = $totalKpiHours - $pastKpiHours;
        $staffRequired      = $this->computeStaffRequired($dept, $hourStartInventory, $remainingKpiHours);

        $kpiHours = $this->computeKpiHours(
            $actualSlot ? $actualSlot['start'] : $slotStart,
            $actualSlot ? $actualSlot['end']   : $slotEnd,
            $breaks,
        );

        $target = $this->computeTarget(
            $staffRequired, $kpiPerHour, $kpiHours,
            $hourStartInventory, $record->hour_index, $lastHourIndex, $record->target
        );

        // ── Build update data ────────────────────────────────
        $record->update([
            'actual'               => $actual,
            'staff'                => $staff,
            'staff_required'       => $staffRequired,
            'target'               => $target,
            'hour_start_inventory' => $hourStartInventory,
            'kpi_hours'            => $kpiHours,
            'efficiency'           => $target > 0
                ? round(($actual / $target) * 100, 1)
                : 0,
            'status'               => HourlyRecordStatus::Active->value,
        ]);
    }

    /**
     * Compute target = staff_required × kpi_per_hour × kpi_hours.
     * Cap at inventory for the last slot.
     */
    private function computeTarget(
        ?int $staffRequired,
        float $kpiPerHour,
        float $kpiHours,
        int $hourStartInventory,
        int $hourIndex,
        int $lastHourIndex,
        int $fallbackTarget,
    ): int {
        $target = ($staffRequired !== null && $staffRequired > 0)
            ? (int) round($staffRequired * $kpiPerHour * $kpiHours)
            : $fallbackTarget;

        // Last slot: cap target at inventory if lower
        if ($hourIndex === $lastHourIndex && $hourStartInventory < $target) {
            $target = $hourStartInventory;
        }

        return $target;
    }

    /**
     * Compute staff_required = ceil(inventory / remaining_kpi_hours / kpi_per_hour).
     */
    private function computeStaffRequired(Department $dept, int $inventory, float $remainingKpiHours): ?int
    {
        // Per-machine: default 1 staff
        if ($dept->productivity_type === ProductivityType::PerMachine) {
            return 1;
        }

        $kpiPerHour = $dept->kpi_per_hour ?? 0;

        if ($inventory <= 0 || $remainingKpiHours <= 0 || $kpiPerHour <= 0) {
            return $inventory <= 0 ? 0 : null;
        }

        return (int) ceil($inventory / $remainingKpiHours / $kpiPerHour);
    }

    /**
     * Refresh day_start_inventory from FPlatform ton_dau.
     */
    private function refreshDayStartInventory(
        ShiftDetail $detail,
        Team $team,
        array $allInventory,
    ): int {
        $teamData = $allInventory['teams'][$team->value] ?? null;
        $tonDau = (int) ($teamData['ton_dau'] ?? 0);

        if ($tonDau > 0 && $tonDau !== $detail->day_start_inventory) {
            Log::info('[SyncHourlyRecords] Updated day_start_inventory', [
                'department' => $detail->department_id,
                'old'        => $detail->day_start_inventory,
                'new'        => $tonDau,
            ]);
            $detail->update(['day_start_inventory' => $tonDau]);
        }

        return $tonDau > 0 ? $tonDau : $detail->day_start_inventory;
    }

    /** Sum values from hourly metrics result. */
    private function sumHourlyValues(array $hours, string $field = 'value'): int
    {
        $sum = 0;
        foreach ($hours as $hour) {
            $sum += (int) ($hour[$field] ?? 0);
        }

        return $sum;
    }

    /**
     * Parse hour_slot label into full-hour Carbon boundaries.
     * "6h-7h" → [Carbon(06:00:00), Carbon(07:00:00)]
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parseHourSlot(string $hourSlot, string $shiftDate): array
    {
        $parts = explode('-', str_replace('h', '', $hourSlot));

        $startHour = (int) $parts[0];
        $endHour = (int) $parts[1];

        $queryStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$shiftDate} {$startHour}:00:00");
        $queryEnd = Carbon::createFromFormat('Y-m-d H:i:s', "{$shiftDate} {$endHour}:00:00");

        return [$queryStart, $queryEnd];
    }

    /**
     * Compute kpi_hours from hour_slot label (fallback when slot map unavailable).
     */
    private function computeKpiHoursFromLabel(string $hourSlot, string $shiftDate, array $breaks): float
    {
        [$start, $end] = $this->parseHourSlot($hourSlot, $shiftDate);
        return $this->computeKpiHours($start, $end, $breaks);
    }
}
