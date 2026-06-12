<?php

namespace App\Containers\AppSection\Production\Jobs;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\FplatformData\Actions\GetHourlyMetricsAction;
use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\FplatformData\Services\CutHourlyImageAllocator;
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
 * Sync hourly records for a single department — dispatched in parallel.
 *
 * Batch-fetches all FPlatform metrics once (3 API calls),
 * then distributes data to individual hourly slots.
 */
final class SyncDepartmentHourlyJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const DEPT_TEAM_MAP = [
        'print'     => Team::Print,
        'cut'       => Team::Cut,
        'pick'      => Team::Pick,      // FLS: pick under DTF line
        'pick_dtf'  => Team::Pick,      // PD: pick child under Pick line
        'mockup'    => Team::Mockup,
        'pack_ship' => Team::PackShip,
        'pick_dtg'  => Team::PickDtg,
        'dtg_print' => Team::DtgPrint,
    ];

    /** Maps department code → hotshot team key in allInventory */
    private const DEPT_HOTSHOT_MAP = [
        'print'     => 'hotshot_print',
        'cut'       => 'hotshot_cut',
        'pick'      => 'hotshot_pick',      // FLS
        'pick_dtf'  => 'hotshot_pick',      // PD: same FPlatform hotshot key
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
        ProductionCacheKeys::flushForDepartment($shift, $dept);

        Log::debug('[SyncDepartmentHourly] Cache invalidated', [
            'shift_id'      => $shift->id,
            'department_id' => $dept->id,
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

        // Ceil to end of last hour slot so the FPlatform query range
        // covers the full final slot (e.g. 14:30 → 15:00 captures all
        // data in the 14h-15h slot, not just 14:00-14:30).
        if ($deptEnd->minute > 0) {
            $deptEnd->startOfHour()->addHour();
        }

        $records = HourlyRecord::where('shift_id', $shift->id)
            ->where('department_id', $dept->id)
            ->orderBy('hour_index')
            ->get();

        $dayStartInventory = $this->refreshDayStartInventory($detail, $team, $allInventory);
        $this->syncHotshotData($detail, $dept, $allInventory);

        // ── Pre-compute shared values ──
        $isPerMachineDtg  = $dept->productivity_type?->isPerMachineDtg() ?? false;
        $isPerMachineDtf  = $dept->productivity_type?->isPerMachineDtf() ?? false;

        // Always use shift_detail snapshot KPI for consistency
        $kpiPerHour = $detail->kpi_per_hour ?? ($dept->kpi_per_hour ?? 0);
        $defaultHeadcount = $detail->headcount ?? 0;
        // Target multiplier: DTF → machine_count, per_person → headcount
        $defaultTargetMultiplier = $isPerMachineDtf ? ($detail->machine_count ?? 0) : $defaultHeadcount;
        $machineStaff     = $isPerMachineDtg ? $this->countMachinesFromInventory($team, $allInventory) : null;

        // Build per-machine KPI map for DTG: [strtolower(code) => kpi_per_hour]
        $machineKpiMap = null;
        if ($isPerMachineDtg) {
            $machineKpiMap = $detail->machines()
                ->with('machine')
                ->get()
                ->mapWithKeys(fn ($sdm) => [
                    strtolower($sdm->machine?->code ?? '') => $sdm->kpi_per_hour,
                ])
                ->all();
        }

        // ── Batch-fetch FPlatform data (3 API calls) ──
        $shiftStart = $deptStart->format('Y-m-d H:i:s');
        $shiftEnd   = $deptEnd->format('Y-m-d H:i:s');

        // CUT team uses time-proportional image allocation instead of simple SUM per scan hour.
        // Single fetch: queries FPlatform once, computes both aggregate + per-user allocations.
        if ($team === Team::Cut) {
            [$productivityMap, $productivityDetailMap] = $this->fetchCutMetrics($detail, $shiftDate, $shiftStart, $shiftEnd);
        } else {
            $productivityMap       = $this->fetchAndIndexByHour($hourlyMetricsAction, $team, HourlyMetricType::Productivity, $shiftStart, $shiftEnd);
            // Team::Print always uses MachineProductivity (DTF groups by machine, not staff).
            // Other teams fall back to department productivity_type setting.
            $detailMetric = $team->supportsMetric(HourlyMetricType::MachineProductivity)
                ? HourlyMetricType::MachineProductivity
                : ($isPerMachineDtg ? HourlyMetricType::MachineProductivity : HourlyMetricType::StaffProductivity);

            $productivityDetailMap = $this->fetchAndGroupByHour(
                $hourlyMetricsAction, $team,
                $detailMetric,
                $shiftStart, $shiftEnd,
            );
        }
        $staffCountMap = $this->fetchAndIndexByHour($hourlyMetricsAction, $team, HourlyMetricType::StaffCount, $shiftStart, $shiftEnd);

        // ── Sync actual data from FPlatform ──
        // target, staff_required, kpi_minutes/hours/percent are manual-only — NOT touched.
        // Only update: staff, actual, efficiency, hour_start_inventory, status, productivity_json.
        $updated = 0;
        $currentInv = $dayStartInventory;

        // ── End-of-shift finalization ──
        // If we're past the last slot of this department's shift,
        // bulk-finalize any remaining non-completed records.
        $isShiftEnded = $now >= $deptEnd;
        if ($isShiftEnded) {
            $staleCount = $records
                ->where('status', '!=', HourlyRecordStatus::Completed->value)
                ->count();

            if ($staleCount > 0) {
                HourlyRecord::where('shift_id', $shift->id)
                    ->where('department_id', $dept->id)
                    ->where('status', '!=', HourlyRecordStatus::Completed->value)
                    ->update(['status' => HourlyRecordStatus::Completed->value]);

                // Refresh in-memory status so the loop below uses correct values
                $records->each(fn ($r) => $r->status = HourlyRecordStatus::Completed->value);

                Log::info("[SyncDepartmentHourly] Finalized {$staleCount} stale records for {$dept->code}.");
            }
        }

        foreach ($records as $record) {
            [$queryStart, $queryEnd] = $this->parseHourSlot($record->hour_slot, $shiftDate);

            $activationTime = $deptStart->gt($queryStart) ? $deptStart->copy() : $queryStart->copy();
            $hourKey        = $queryStart->format('Y-m-d H');

            $isFutureSlot  = $now < $activationTime;
            $isCurrentSlot = !$isFutureSlot && $now < $queryEnd;
            $isPassedSlot  = $now >= $queryEnd;

            $hourStartInventory = max(0, $currentInv);

            // ── Future slot: update inventory + clean stale data ──
            // After a schedule change (e.g. start_time shifted later),
            // SyncHourlyRecordsTask may leave stale actual/status from the
            // old hour_index mapping. Ensure future slots are always clean.
            if ($isFutureSlot) {
                $updates = [];

                if ($record->hour_start_inventory !== $hourStartInventory) {
                    $updates['hour_start_inventory'] = $hourStartInventory;
                }

                // Reset stale production data on future slots
                if ($record->actual !== null) {
                    $updates['actual'] = null;
                    $updates['efficiency'] = 0;
                    $updates['productivity_json'] = null;
                }
                if ($record->staff !== null) {
                    $updates['staff'] = null;
                }
                if ($record->status !== HourlyRecordStatus::Pending->value) {
                    $updates['status'] = HourlyRecordStatus::Pending->value;
                }

                if (!empty($updates)) {
                    $record->update($updates);
                    $updated++;
                }

                // Advance inventory by effective target (manual target or estimate)
                $currentInv = max(0, $currentInv - TargetEstimator::effective(
                    $record->target, $kpiPerHour, $record->kpi_percent ?? 100,
                    $isPerMachineDtg, $isPerMachineDtf
                        ? ($record->machine_count ?? $defaultTargetMultiplier)
                        : ($record->staff_required ?? $defaultHeadcount),
                ));
                continue;
            }

            // ── Active / Passed / Completed slots: sync FPlatform data ──
            $actual = (int) ($productivityMap[$hourKey] ?? 0);
            $staff  = $isPerMachineDtg
                ? $machineStaff
                : (int) ($staffCountMap[$hourKey] ?? 0);
            $productivityJson = HourlyRecord::stampItemIds($productivityDetailMap[$hourKey] ?? null);
            $productivityJson = HourlyRecord::injectItemEfficiency($productivityJson, $kpiPerHour, $machineKpiMap);

            $effectiveTarget = TargetEstimator::effective(
                $record->target, $kpiPerHour, $record->kpi_percent ?? 100,
                $isPerMachineDtg, $isPerMachineDtf
                    ? ($record->machine_count ?? $defaultTargetMultiplier)
                    : ($record->staff_required ?? $defaultHeadcount),
            );

            $status = $isPassedSlot || $record->status === HourlyRecordStatus::Completed->value
                ? HourlyRecordStatus::Completed
                : ($isCurrentSlot ? HourlyRecordStatus::Active : HourlyRecordStatus::Pending);

            $record->update([
                'actual'               => $actual,
                'staff'                => $staff,
                'hour_start_inventory' => $hourStartInventory,
                'efficiency'           => $effectiveTarget > 0 && $actual > 0
                    ? round(($actual / $effectiveTarget) * 100, 1)
                    : 0,
                'status'               => $status->value,
                'productivity_json'    => $productivityJson,
            ]);
            $updated++;

            // Advance inventory: passed/completed → subtract actual, active → subtract target
            $currentInv = ($isPassedSlot || $status === HourlyRecordStatus::Completed)
                ? max(0, $currentInv - $actual)
                : max(0, $currentInv - $effectiveTarget);
        }

        return $updated;
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

    /**
     * Fetch CUT productivity using time-proportional image allocation.
     *
     * Queries FPlatform ONCE and computes both aggregate (productivityMap)
     * and per-user (productivityDetailMap) allocations in a single pass.
     *
     * @return array{0: array<string, int>, 1: array<string, array>}
     *         [productivityMap, productivityDetailMap]
     */
    private function fetchCutMetrics(ShiftDetail $detail, string $shiftDate, string $shiftStart, string $shiftEnd): array
    {
        $breaks = CutHourlyImageAllocator::extractBreaks($detail, $shiftDate);
        $factory = FactoryLine::current();
        $allocator = app(CutHourlyImageAllocator::class);
        $logTask = app(\App\Containers\AppSection\FplatformData\Tasks\GetLogFileCutTask::class);

        // Single remote query
        $logs = $logTask->run($shiftStart, $shiftEnd, $factory);

        // Single-pass: both aggregate + per-user allocations
        [$productivityMap, $perUserItems] = $allocator->allocateBoth(
            $logs, $shiftDate, $detail->start_time, $breaks,
        );

        // Group per-user items by hourKey for productivityDetailMap
        $productivityDetailMap = [];
        foreach ($perUserItems as $item) {
            $productivityDetailMap[$item['date_hour']][] = $item;
        }

        return [$productivityMap, $productivityDetailMap];
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
}
