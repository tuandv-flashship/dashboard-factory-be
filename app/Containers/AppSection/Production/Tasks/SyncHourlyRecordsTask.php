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
use Illuminate\Support\Facades\Log;

/**
 * Sync hourly_records with real-time data from FPlatform.
 *
 * Handles both status lifecycle (pending → active → completed)
 * and FPlatform data sync (actual, staff, inventory, efficiency).
 *
 * For each department's hourly records:
 * - Pending slots whose time has arrived → activate + fetch data
 * - Active current slot → refresh data from FPlatform
 * - Active past slots with no data → fetch data + complete
 * - Active past slots with data → complete
 * - Already completed → skip
 *
 * Called by SyncHourlyRecordsJob every N minutes.
 */
final class SyncHourlyRecordsTask extends ParentTask
{
    use ComputesKpiHours;
    /**
     * Department code → FPlatform Team mapping.
     * Same as FetchDailyInventoryForShiftTask::DEPT_TEAM_MAP.
     */
    private const DEPT_TEAM_MAP = [
        'print'     => Team::Print,
        'cut'       => Team::Cut,
        'pick'      => Team::Pick,
        'mockup'    => Team::Mockup,
        'pack_ship' => Team::PackShip,
        'pick_dtg'  => Team::PickDtg,
        'dtg_print' => Team::DtgPrint,
    ];

    /**
     * Max hours to retry fetching data for a past slot before force-completing.
     * After this grace period, slots with actual=0 are completed as-is.
     */
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
        // ── 1. Resolve shift ─────────────────────────────────
        $shift = ($date || $shiftNumber)
            ? Shift::resolve($date, $shiftNumber)
            : Shift::current();

        if (!$shift) {
            $msg = '[SyncHourlyRecords] No shift found — skipped.';
            Log::info($msg, ['date' => $date ?? now()->toDateString(), 'shift' => $shiftNumber]);

            return ['synced' => 0, 'shift' => null, 'message' => 'No shift found.'];
        }

        $shiftDate = $shift->date->toDateString();

        // ── 2. Load shift details with departments ───────────
        $shiftDetails = ShiftDetail::with('department')
            ->where('shift_id', $shift->id)
            ->get();

        if ($shiftDetails->isEmpty()) {
            return ['synced' => 0, 'shift' => $shift, 'message' => 'No shift details found.'];
        }

        // ── 3. Bulk fetch all teams inventory (cached 5min) ──
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
     * Handles status transitions AND data fetching in one pass.
     * Past slots are retried if actual=0 (up to COMPLETE_GRACE_HOURS after slot end).
     *
     * Time boundaries:
     * - Activation: based on department start_time (e.g., 06:30 for print)
     * - FPlatform query: based on hour_slot full-hour boundaries (e.g., 06:00-07:00)
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

        // Department actual start time (for activation check)
        $deptStart = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $shiftDate . ' ' . $detail->start_time
        );

        // Load all records for this department, ordered by hour_index
        $records = HourlyRecord::where('shift_id', $shift->id)
            ->where('department_id', $dept->id)
            ->orderBy('hour_index')
            ->get();

        // ── Refresh day_start_inventory from ton_dau ─────────
        $dayStartInventory = $this->refreshDayStartInventory($detail, $team, $allInventory);

        // ── Collect breaks for kpi_hours recalculation ────────
        $breaks = $this->collectBreaks($detail);

        // ── Build actual slot time map (for kpi_hours partial slots) ──
        $deptWorkStart = Carbon::createFromFormat('H:i:s', $detail->start_time);
        $deptWorkEnd   = $deptWorkStart->copy()->addMinutes((int) ($detail->work_hours * 60));
        $actualSlotMap = $this->buildActualSlotMap($deptWorkStart, $deptWorkEnd);

        // ── Pass 1: Recalculate all kpi_hours first ──────────
        // Must be done before computing totalKpiHours to avoid stale values
        foreach ($records as $record) {
            [$qStart, $qEnd] = $this->parseHourSlot($record->hour_slot, $shiftDate);
            $actualSlot = $actualSlotMap[$record->hour_index] ?? null;
            $kpiHours = $this->computeKpiHours(
                $actualSlot ? $actualSlot['start'] : $qStart,
                $actualSlot ? $actualSlot['end']   : $qEnd,
                $breaks,
            );

            if ($record->kpi_hours != $kpiHours) {
                $record->update(['kpi_hours' => $kpiHours]);
                $record->kpi_hours = $kpiHours;
            }
        }

        // ── Compute total KPI hours (now accurate after pass 1) ──
        $totalKpiHours = $records->sum('kpi_hours');

        // ── Pass 2: Sync data from FPlatform ─────────────────
        $updated = 0;

        foreach ($records as $record) {
            [$queryStart, $queryEnd] = $this->parseHourSlot($record->hour_slot, $shiftDate);

            $activationTime = $deptStart->gt($queryStart) ? $deptStart->copy() : $queryStart->copy();
            $actualSlot = $actualSlotMap[$record->hour_index] ?? null;

            // ── Slot hasn't started yet → stay pending ───────
            if ($now < $activationTime) {
                continue;
            }

            // ── Already completed → skip FPlatform sync ──────
            // Always recalculate staff_required + target + efficiency (no API call, pure math)
            if ($record->status === HourlyRecordStatus::Completed->value) {
                $pastKpiHours = $records
                    ->where('hour_index', '<', $record->hour_index)
                    ->sum('kpi_hours');
                $remainingKpiHours = $totalKpiHours - $pastKpiHours;
                $staffRequired = $this->computeStaffRequired($dept, $record->hour_start_inventory, $remainingKpiHours);

                $isPerMachine = $dept->productivity_type === ProductivityType::PerMachine;
                $kpiPerHour = $isPerMachine ? ($detail->kpi_per_hour ?? 0) : ($dept->kpi_per_hour ?? 0);
                $target = ($staffRequired !== null && $staffRequired > 0)
                    ? (int) round($staffRequired * $kpiPerHour * $record->kpi_hours)
                    : $record->target;

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

            $isCurrentSlot = $now >= $activationTime && $now < $queryEnd;
            $isPassedSlot = $now >= $queryEnd;

            if ($isCurrentSlot) {
                // ── Current slot: fetch data + set active ────
                $this->fetchAndUpdateRecord($record, $team, $dept, $detail, $queryStart, $queryEnd, $dayStartInventory, $records, $totalKpiHours, $breaks, $actualSlot);
                $updated++;
            } elseif ($isPassedSlot) {
                // ── Past slot: always fetch latest data ──────
                $this->fetchAndUpdateRecord($record, $team, $dept, $detail, $queryStart, $queryEnd, $dayStartInventory, $records, $totalKpiHours, $breaks, $actualSlot);

                // Complete only if we have data OR grace period expired
                $record->refresh();
                $graceExpired = $now->diffInHours($queryEnd) >= self::COMPLETE_GRACE_HOURS;

                if ($record->actual > 0 || $graceExpired) {
                    $record->update(['status' => HourlyRecordStatus::Completed->value]);
                }
                // else: keep active → retry on next sync cycle

                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Fetch FPlatform data and update a single hourly record.
     *
     * Sets: actual, staff, efficiency, status=active.
     * Calculates hour_start_inventory = dayStartInventory - Σ actual of previous slots.
     *
     * @param \Illuminate\Support\Collection            $records       All records for this department
     * @param float                                      $totalKpiHours Total KPI hours for the dept in this shift
     * @param array<array{start: Carbon, end: Carbon}>  $breaks        Break periods for kpi_hours
     * @param array{start: Carbon, end: Carbon}|null     $actualSlot    Actual slot times (for partial slot kpi_hours)
     */
    private function fetchAndUpdateRecord(
        HourlyRecord $record,
        Team $team,
        Department $dept,
        ShiftDetail $detail,
        Carbon $slotStart,
        Carbon $slotEnd,
        int $dayStartInventory,
        $records,
        float $totalKpiHours,
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

        // ── Calculate hour_start_inventory ────────────────────
        // = day_start_inventory - sum(actual of all previous slots)
        $previousActual = $records
            ->where('hour_index', '<', $record->hour_index)
            ->sum('actual');

        $hourStartInventory = max(0, $dayStartInventory - (int) $previousActual);

        // ── Calculate staff_required ─────────────────────────
        // remaining_kpi_hours = totalKpiHours − Σ(kpi_hours of past slots)
        $pastKpiHours = $records
            ->where('hour_index', '<', $record->hour_index)
            ->sum('kpi_hours');

        $remainingKpiHours = $totalKpiHours - $pastKpiHours;
        $staffRequired = $this->computeStaffRequired($dept, $hourStartInventory, $remainingKpiHours);

        // ── Recalculate target = staff_required × kpi_per_hour × kpi_hours ──
        $kpiHours = $this->computeKpiHours(
            $actualSlot ? $actualSlot['start'] : $slotStart,
            $actualSlot ? $actualSlot['end']   : $slotEnd,
            $breaks,
        );

        $isPerMachine = $dept->productivity_type === ProductivityType::PerMachine;
        $kpiPerHour = $isPerMachine ? ($detail->kpi_per_hour ?? 0) : ($dept->kpi_per_hour ?? 0);
        $target = ($staffRequired !== null && $staffRequired > 0)
            ? (int) round($staffRequired * $kpiPerHour * $kpiHours)
            : $record->target;

        // ── Build update data ────────────────────────────────
        $updateData = [
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
        ];

        $record->update($updateData);
    }

    /**
     * Compute staff_required = ceil(inventory / remaining_kpi_hours / kpi_per_hour).
     *
     * Returns null for per_machine departments or when denominator is 0.
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
     *
     * Updates shift_details.day_start_inventory if ton_dau > 0 and changed.
     *
     * @return int The effective day_start_inventory value
     */
    private function refreshDayStartInventory(
        ShiftDetail $detail,
        Team $team,
        array $allInventory,
    ): int {
        $teamData = $allInventory['teams'][$team->value] ?? null;
        $tonDau = (int) ($teamData['ton_dau'] ?? 0);

        // Only update if ton_dau > 0 AND different from current value
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

    /**
     * Sum values from hourly metrics result.
     * Hours array: [['date_hour' => '...', 'value' => 123], ...]
     */
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
     *
     * "6h-7h"  → [Carbon(06:00:00), Carbon(07:00:00)]
     * "13h-14h" → [Carbon(13:00:00), Carbon(14:00:00)]
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parseHourSlot(string $hourSlot, string $shiftDate): array
    {
        // "6h-7h" → ["6", "7"]
        $parts = explode('-', str_replace('h', '', $hourSlot));

        $startHour = (int) $parts[0];
        $endHour = (int) $parts[1];

        $queryStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$shiftDate} {$startHour}:00:00");
        $queryEnd = Carbon::createFromFormat('Y-m-d H:i:s', "{$shiftDate} {$endHour}:00:00");

        return [$queryStart, $queryEnd];
    }

    /**
     * Build a map of hour_index → actual slot {start, end} times.
     *
     * Uses the same aligned-slot logic as GenerateHourlyRecordsTask
     * to reconstruct actual slot boundaries (important for partial first/last slots).
     *
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    private function buildActualSlotMap(Carbon $start, Carbon $end): array
    {
        $map = [];
        $index = 0;

        $firstFullHour = $start->copy()->startOfHour();
        if ($firstFullHour->lt($start)) {
            $firstFullHour->addHour();
        }

        $lastFullHour = $end->copy()->startOfHour();

        // Partial first slot
        if ($start->minute > 0 && $firstFullHour->lte($end)) {
            $slotEnd = $firstFullHour->copy()->min($end);
            $map[$index++] = ['start' => $start->copy(), 'end' => $slotEnd->copy()];
        }

        // Full-hour slots
        $cursor = $firstFullHour->copy();
        while ($cursor->lt($lastFullHour)) {
            $slotEnd = $cursor->copy()->addHour();
            $map[$index++] = ['start' => $cursor->copy(), 'end' => $slotEnd->copy()];
            $cursor->addHour();
        }

        // Partial last slot
        if ($end->minute > 0 && $lastFullHour->gte($firstFullHour)) {
            $map[$index] = ['start' => $lastFullHour->copy(), 'end' => $end->copy()];
        }

        return $map;
    }
}
