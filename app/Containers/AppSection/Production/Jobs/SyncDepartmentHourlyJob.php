<?php

namespace App\Containers\AppSection\Production\Jobs;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\FplatformData\Actions\GetHourlyMetricsAction;
use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Traits\ComputesKpiHours;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sync hourly records for a single department — dispatched in parallel.
 *
 * Batch-fetches all FPlatform metrics once (3 API calls),
 * then distributes data to individual hourly slots.
 */
final class SyncDepartmentHourlyJob implements ShouldQueue
{
    use Batchable;
    use ComputesKpiHours;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const DEPT_TEAM_MAP = [
        'print'     => Team::Print,
        'cut'       => Team::Cut,
        'pick'      => Team::Pick,
        'mockup'    => Team::Mockup,
        'pack_ship' => Team::PackShip,
        'pick_dtg'  => Team::PickDtg,
        'dtg_print' => Team::DtgPrint,
    ];

    /** Maps department code → hotshot team key in allInventory */
    private const DEPT_HOTSHOT_MAP = [
        'print'     => 'hotshot_print',
        'cut'       => 'hotshot_cut',
        'pick'      => 'hotshot_pick',
        'mockup'    => 'hotshot_mockup',
        'pack_ship' => 'hotshot_pack_ship',
    ];

    public function __construct(
        private readonly int $shiftId,
        private readonly int $shiftDetailId,
        private readonly array $allInventory,
    ) {
    }

    public function handle(GetHourlyMetricsAction $hourlyMetricsAction): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $shift = Shift::find($this->shiftId);
        $detail = ShiftDetail::with('department')->find($this->shiftDetailId);

        if (!$shift || !$detail || !$detail->department) {
            return;
        }

        $dept = $detail->department;
        $team = self::DEPT_TEAM_MAP[$dept->code] ?? null;

        if (!$team) {
            return;
        }

        try {
            $synced = $this->syncDepartment(
                $shift, $detail, $dept, $team,
                $this->allInventory, $hourlyMetricsAction,
            );

            if ($synced > 0) {
                Log::info("[SyncDepartmentHourly] Synced {$synced} records for {$dept->code}.");
                $this->invalidateCache($shift, $dept);
            }
        } catch (\Throwable $e) {
            Log::warning('[SyncDepartmentHourly] Failed', [
                'department' => $dept->code,
                'error'      => $e->getMessage(),
            ]);

            throw $e; // Let the batch handle the failure
        }
    }

    /**
     * Flush cached API responses for the synced date+shift so the
     * next request always returns fresh numbers after a sync completes.
     *
     * Only historical dates are ever cached, so today's requests
     * are always live and don't need flushing.
     */
    private function invalidateCache(Shift $shift, Department $dept): void
    {
        $date      = $shift->date->toDateString();
        $shiftNum  = $shift->shift_number;

        if (!ProductionCacheKeys::isHistorical($date)) {
            return; // live data is never cached — nothing to flush
        }

        $line = $dept->productionLine?->code;

        $keys = array_filter([
            $line ? ProductionCacheKeys::deptDetail($line, $dept->code, $date, $shiftNum) : null,
            $line ? ProductionCacheKeys::lineSummary($line, $date, $shiftNum)             : null,
            ProductionCacheKeys::quality($date, $shiftNum),
        ]);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Log::debug('[SyncDepartmentHourly] Cache invalidated', [
            'keys' => array_values($keys),
        ]);
    }

    private function syncDepartment(
        Shift $shift,
        ShiftDetail $detail,
        Department $dept,
        Team $team,
        array $allInventory,
        GetHourlyMetricsAction $hourlyMetricsAction,
    ): int {
        $now = now();
        $shiftDate = $shift->date->toDateString();

        $deptStart = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $shiftDate . ' ' . $detail->start_time
        );
        $deptEnd = $deptStart->copy()->addMinutes((int) ($detail->work_hours * 60) + ($detail->meal_break_minutes ?? 0));

        $records = HourlyRecord::where('shift_id', $shift->id)
            ->where('department_id', $dept->id)
            ->orderBy('hour_index')
            ->get();

        $dayStartInventory = $this->refreshDayStartInventory($detail, $team, $allInventory);
        $this->syncHotshotData($detail, $dept, $allInventory);
        $breaks = $this->collectBreaks($detail, $detail->start_time);

        // Build slot map — reuse $deptStart/$deptEnd (already computed above)
        // buildAlignedSlots accepts Carbon objects directly, no need to re-instantiate.
        $slotMap = $this->buildAlignedSlots($deptStart, $deptEnd);

        // ── Pass 1: Recalculate kpi fields ──
        foreach ($records as $record) {
            $slot = $slotMap[$record->hour_index] ?? null;

            $kpiData = $slot
                ? $this->computeKpiHoursData($slot['start'], $slot['end'], $breaks)
                : $this->computeKpiHoursDataFromLabel($record->hour_slot, $shiftDate, $breaks);

            if ($record->kpi_minutes !== $kpiData['minutes']) {
                $record->update([
                    'kpi_hours'   => $kpiData['hours'],
                    'kpi_minutes' => $kpiData['minutes'],
                    'kpi_percent' => $kpiData['percent'],
                ]);
                $record->kpi_hours   = $kpiData['hours'];
                $record->kpi_minutes = $kpiData['minutes'];
                $record->kpi_percent = $kpiData['percent'];
            }
        }

        // ── Pre-compute shared values ──
        $totalKpiMinutes  = $records->sum('kpi_minutes');  // integer, exact
        $isPerMachine     = $dept->productivity_type === ProductivityType::PerMachine;
        $kpiPerHour       = $isPerMachine ? ($detail->kpi_per_hour ?? 0) : ($dept->kpi_per_hour ?? 0);
        $machineCount     = $isPerMachine ? $detail->machines()->count() : null; // cached once
        $machineStaff     = $isPerMachine ? $this->countMachinesFromInventory($team, $allInventory) : null; // constant for the whole shift

        // ── Batch-fetch FPlatform data (3 API calls) ──
        $shiftStart = $deptStart->format('Y-m-d H:i:s');
        $shiftEnd   = $deptEnd->format('Y-m-d H:i:s');

        $productivityMap       = $this->fetchAndIndexByHour($hourlyMetricsAction, $team, HourlyMetricType::Productivity, $shiftStart, $shiftEnd);
        $staffCountMap         = $this->fetchAndIndexByHour($hourlyMetricsAction, $team, HourlyMetricType::StaffCount, $shiftStart, $shiftEnd);
        $productivityDetailMap = $this->fetchAndGroupByHour(
            $hourlyMetricsAction, $team,
            $isPerMachine ? HourlyMetricType::MachineProductivity : HourlyMetricType::StaffProductivity,
            $shiftStart, $shiftEnd,
        );

        // ── Pass 2: Sync data (no more API calls) ──
        $updated = 0;

        // Running inventory: decremented slot-by-slot.
        // Past/completed slots: subtract actual (real output).
        // Future/active slots: subtract target (projected output).
        $cumulativeKpiMinutes = [];
        $runKpiMinutes = 0;
        foreach ($records as $record) {
            $cumulativeKpiMinutes[$record->hour_index] = $runKpiMinutes;
            $runKpiMinutes += (int) $record->kpi_minutes;
        }

        $currentInv = $dayStartInventory; // ← single source of truth, updated each slot
        $currentSlotStaffRequired = null; // staff_required of the currently active slot

        foreach ($records as $record) {
            [$queryStart, $queryEnd] = $this->parseHourSlot($record->hour_slot, $shiftDate);

            $activationTime = $deptStart->gt($queryStart) ? $deptStart->copy() : $queryStart->copy();
            $actualSlot     = $slotMap[$record->hour_index] ?? null;
            $hourKey        = $queryStart->format('Y-m-d H');

            $isFutureSlot  = $now < $activationTime;
            $isCurrentSlot = !$isFutureSlot && $now < $queryEnd;
            $isPassedSlot  = $now >= $queryEnd;

            $pastKpiMinutes      = $cumulativeKpiMinutes[$record->hour_index] ?? 0;
            $hourStartInventory  = max(0, $currentInv);
            $remainingKpiMinutes = $totalKpiMinutes - $pastKpiMinutes;
            $staffRequired       = $this->computeStaffRequired($dept, $hourStartInventory, $remainingKpiMinutes, $detail, $machineCount);

            // Capture staff_required of the currently active slot for future slots to inherit
            if ($isCurrentSlot) {
                $currentSlotStaffRequired = $staffRequired;
            }

            // ── Future slot: compute target only, preserve actual=0/null ──
            if ($isFutureSlot) {
                // Use current slot's staff_required so future slots reflect the same
                // headcount needed right now, rather than a per-slot recomputation.
                // However, if inventory is already exhausted, no staff is needed.
                $futureStaffRequired = $hourStartInventory <= 0
                    ? 0
                    : ($currentSlotStaffRequired ?? $staffRequired);

                $target = $this->computeTarget(
                    $futureStaffRequired, $kpiPerHour, $record->kpi_percent,
                    $hourStartInventory, $record->target, $isPerMachine
                );

                if ($record->target !== $target || $record->hour_start_inventory !== $hourStartInventory || $record->staff_required !== $futureStaffRequired) {
                    $record->update([
                        'target'               => $target,
                        'staff_required'       => $futureStaffRequired,
                        'hour_start_inventory' => $hourStartInventory,
                    ]);
                    $updated++;
                }

                // Future slot: assume target will be met
                $currentInv = max(0, $currentInv - $target);
                continue;
            }

            $actual           = (int) ($productivityMap[$hourKey] ?? 0);
            $staff            = $isPerMachine
                ? $machineStaff
                : (int) ($staffCountMap[$hourKey] ?? 0);
            $productivityJson = $productivityDetailMap[$hourKey] ?? null;

            // ── Already completed → re-sync actual + recalc metrics ──
            if ($record->status === HourlyRecordStatus::Completed->value) {
                // Completed (past) slots: target = staff × kpi_per_hour × kpi_percent, capped by inventory
                // For per_machine: kpi_per_hour is already total capacity — do NOT multiply by machine count.
                $target = $staff > 0
                    ? min($hourStartInventory, (int) round(($isPerMachine ? 1 : $staff) * $kpiPerHour * $record->kpi_percent / 100))
                    : $this->computeTarget(
                        $staffRequired, $kpiPerHour, $record->kpi_percent,
                        $hourStartInventory, $record->target, $isPerMachine
                    );

                $record->update([
                    'actual'               => $actual,
                    'staff'                => $staff,
                    'staff_required'       => $staffRequired,
                    'target'               => $target,
                    'hour_start_inventory' => $hourStartInventory,
                    'efficiency'           => $target > 0 && $actual > 0
                        ? round(($actual / $target) * 100, 1)
                        : 0,
                    'productivity_json'    => $productivityJson,
                ]);

                // Completed slot: subtract real actual output
                $currentInv = max(0, $currentInv - $actual);
                $updated++;
                continue;
            }

            // ── Active / Passed slots ──
            if ($isCurrentSlot || $isPassedSlot) {
                $target = $this->updateRecord(
                    $record, $dept, $detail,
                    $hourStartInventory, $remainingKpiMinutes,
                    $actual, $staff, $productivityJson,
                    $kpiPerHour, $machineCount,
                    $breaks, $actualSlot, $isPassedSlot,
                );

                // Passed slot: subtract actual; active (running) slot: subtract target
                $currentInv = $isPassedSlot
                    ? max(0, $currentInv - $actual)
                    : max(0, $currentInv - $target);
                $updated++;
            }

        }

        return $updated;

    }

    /**
     * Update an active or passed hourly record.
     *
     * @param int $hourStartInventory  Pre-computed for this slot (running sequential inv)
     * @param int $remainingKpiMinutes Pre-computed total - past kpi minutes
     * @return int                     Computed target (used by caller to advance $currentInv)
     */
    private function updateRecord(
        HourlyRecord $record,
        Department $dept,
        ShiftDetail $detail,
        int $hourStartInventory,
        int $remainingKpiMinutes,
        int $actual,
        int $staff,
        ?array $productivityJson,
        float $kpiPerHour,
        ?int $cachedMachineCount,   // pass-through from syncDepartment to avoid extra DB query
        array $breaks,
        ?array $actualSlot,
        bool $isCompleted,
    ): int {
        $staffRequired = $this->computeStaffRequired($dept, $hourStartInventory, $remainingKpiMinutes, $detail, $cachedMachineCount);

        $slotStart = $actualSlot ? $actualSlot['start'] : null;
        $slotEnd   = $actualSlot ? $actualSlot['end']   : null;

        if ($slotStart && $slotEnd) {
            $kpiData    = $this->computeKpiHoursData($slotStart, $slotEnd, $breaks);
            $kpiMinutes = $kpiData['minutes'];
            $kpiHours   = $kpiData['hours'];
            $kpiPercent = $kpiData['percent'];
        } else {
            $kpiMinutes = $record->kpi_minutes;
            $kpiHours   = $record->kpi_hours;
            $kpiPercent = $record->kpi_percent;
        }

        $isPerMachine = $dept->productivity_type === ProductivityType::PerMachine;

        // Passed (completed) slots: target = staff × kpi_per_hour × kpi_percent, capped by inventory
        // For per_machine: kpi_per_hour is already total capacity — do NOT multiply by machine count.
        // Active (current) slots: target = staff_required × kpi_per_hour × kpi_percent
        $target = ($isCompleted && $staff > 0)
            ? min($hourStartInventory, (int) round(($isPerMachine ? 1 : $staff) * $kpiPerHour * $kpiPercent / 100))
            : $this->computeTarget(
                $staffRequired, $kpiPerHour, $kpiPercent,
                $hourStartInventory, $record->target, $isPerMachine
            );

        $status = $isCompleted
            ? HourlyRecordStatus::Completed
            : HourlyRecordStatus::Active;

        $record->update([
            'actual'               => $actual,
            'staff'                => $staff,
            'staff_required'       => $staffRequired,
            'target'               => $target,
            'hour_start_inventory' => $hourStartInventory,
            'kpi_hours'            => $kpiHours,
            'kpi_minutes'          => $kpiMinutes,
            'kpi_percent'          => $kpiPercent,
            'efficiency'           => $target > 0
                ? round(($actual / $target) * 100, 1)
                : 0,
            'status'               => $status->value,
            'productivity_json'    => $productivityJson,
        ]);

        return $target;
    }

    // ── Helper methods ──────────────────────────────────

    private function fetchAndIndexByHour(
        GetHourlyMetricsAction $action, Team $team, HourlyMetricType $metric,
        string $shiftStart, string $shiftEnd,
    ): array {
        try {
            $result = $action->run($team, $metric, $shiftStart, $shiftEnd);
        } catch (\InvalidArgumentException) {
            return []; // Metric not supported by this team
        }

        $field = $metric === HourlyMetricType::StaffCount ? 'num_staff' : 'value';
        $map = [];

        foreach ($result['hours'] as $item) {
            $key = $item['date_hour'] ?? '';
            $map[$key] = ($map[$key] ?? 0) + (int) ($item[$field] ?? 0);
        }

        return $map;
    }

    private function fetchAndGroupByHour(
        GetHourlyMetricsAction $action, Team $team, HourlyMetricType $metric,
        string $shiftStart, string $shiftEnd,
    ): array {
        try {
            $result = $action->run($team, $metric, $shiftStart, $shiftEnd);
        } catch (\InvalidArgumentException) {
            return []; // Metric not supported by this team
        }

        $map = [];
        foreach ($result['hours'] as $item) {
            $key = $item['date_hour'] ?? '';
            $map[$key][] = $item;
        }

        return $map;
    }

    /**
     * Compute hourly target for a slot.
     *
     * Per-staff:   target = staffRequired × kpiPerHour × (kpiPercent / 100)
     * Per-machine: target = kpiPerHour × (kpiPercent / 100)
     *              (kpiPerHour is already the TOTAL capacity of all machines combined)
     *
     * @param float $kpiPercent  kpi_percent field (0-100), proportional slot weight
     */
    private function computeTarget(
        ?int $staffRequired, float $kpiPerHour, float $kpiPercent,
        int $hourStartInventory, int $fallbackTarget,
        bool $perMachine = false,
    ): int {
        if ($perMachine) {
            // kpi_per_hour is already the TOTAL capacity of all machines combined.
            // Only produce target if machines are assigned (staffRequired > 0).
            $target = ($staffRequired !== null && $staffRequired > 0)
                ? (int) round($kpiPerHour * $kpiPercent / 100)
                : $fallbackTarget;
        } else {
            $target = ($staffRequired !== null && $staffRequired > 0)
                ? (int) round($staffRequired * $kpiPerHour * $kpiPercent / 100)
                : $fallbackTarget;
        }

        // Cap target by available inventory — every slot, not just the last.
        return min($target, $hourStartInventory);
    }

    private function computeStaffRequired(
        Department $dept,
        int $inventory,
        int $remainingKpiMinutes,
        ?ShiftDetail $detail = null,
        ?int $cachedMachineCount = null,
    ): ?int {
        if ($dept->productivity_type === ProductivityType::PerMachine) {
            if ($cachedMachineCount !== null) {
                return $cachedMachineCount;
            }
            return $detail ? $detail->machines()->count() : 0;
        }

        $kpiPerHour = $dept->kpi_per_hour ?? 0;

        if ($inventory <= 0 || $remainingKpiMinutes <= 0 || $kpiPerHour <= 0) {
            return $inventory <= 0 ? 0 : null;
        }

        // Use minutes directly to avoid float precision loss:
        // staffRequired = ceil(inventory / (remainingKpiMinutes / 60) / kpiPerHour)
        //               = ceil(inventory * 60 / remainingKpiMinutes / kpiPerHour)
        return (int) ceil($inventory * 60 / $remainingKpiMinutes / $kpiPerHour);
    }

    private function refreshDayStartInventory(ShiftDetail $detail, Team $team, array $allInventory): int
    {
        // DtgPrint hourly metrics use Team::DtgPrint,
        // but inventory is stored under Team::DtgPrintSplit key
        $inventoryKey = match ($team) {
            Team::DtgPrint => Team::DtgPrintSplit->value,
            default        => $team->value,
        };

        $teamData = $allInventory['teams'][$inventoryKey] ?? null;
        $tongViec = (int) ($teamData['tong_viec'] ?? 0);

        if ($tongViec > 0 && $tongViec !== $detail->day_start_inventory) {
            $detail->update(['day_start_inventory' => $tongViec]);
        }

        return $tongViec > 0 ? $tongViec : $detail->day_start_inventory;
    }

    /**
     * Sync hotshot_total and hotshot_completed from allInventory into ShiftDetail.
     * DTG departments have no hotshot — defaults to 0.
     */
    private function syncHotshotData(ShiftDetail $detail, Department $dept, array $allInventory): void
    {
        $hotshotKey = self::DEPT_HOTSHOT_MAP[$dept->code] ?? null;

        if (!$hotshotKey) {
            return; // DTG departments — no hotshot
        }

        $hotshotData = $allInventory['teams'][$hotshotKey] ?? null;
        $total = (int) ($hotshotData['tong_viec'] ?? 0);
        $completed = (int) ($hotshotData['da_lam'] ?? 0);

        if ($total !== $detail->hotshot_total || $completed !== $detail->hotshot_completed) {
            $detail->update([
                'hotshot_total'     => $total,
                'hotshot_completed' => $completed,
            ]);
        }
    }

    /**
     * Count machines from FPlatform inventory for per_machine departments.
     * e.g. dtg_print_split has 3 machines (apollo, atlas_1, atlas_2) → staff = 3.
     */
    private function countMachinesFromInventory(Team $team, array $allInventory): int
    {
        $inventoryKey = match ($team) {
            Team::DtgPrint => Team::DtgPrintSplit->value,
            default        => $team->value,
        };

        $teamData = $allInventory['teams'][$inventoryKey] ?? null;

        if (!$teamData || !isset($teamData['machines'])) {
            return 0;
        }

        return count($teamData['machines']);
    }

    private function parseHourSlot(string $hourSlot, string $shiftDate): array
    {
        $parts = explode('-', str_replace('h', '', $hourSlot));
        $startHour = (int) $parts[0];
        $endHour = (int) $parts[1];

        return [
            Carbon::createFromFormat('Y-m-d H:i:s', "{$shiftDate} {$startHour}:00:00"),
            Carbon::createFromFormat('Y-m-d H:i:s', "{$shiftDate} {$endHour}:00:00"),
        ];
    }

    /**
     * Compute full KPI data (minutes, percent, hours) from an hour_slot label.
     *
     * @return array{minutes: int, percent: float, hours: float}
     */
    private function computeKpiHoursDataFromLabel(string $hourSlot, string $shiftDate, array $breaks): array
    {
        [$start, $end] = $this->parseHourSlot($hourSlot, $shiftDate);
        return $this->computeKpiHoursData($start, $end, $breaks);
    }
}
