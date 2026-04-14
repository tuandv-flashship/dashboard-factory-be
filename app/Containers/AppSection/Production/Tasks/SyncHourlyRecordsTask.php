<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\FplatformData\Actions\GetDailyInventoryAction;
use App\Containers\AppSection\FplatformData\Actions\GetHourlyMetricsAction;
use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
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
        private readonly GetDailyInventoryAction $inventoryAction,
    ) {
    }

    public function run(): void
    {
        $today = now()->toDateString();

        // ── 1. Find active shift ─────────────────────────────
        $shift = Shift::current();

        if (!$shift) {
            Log::info('[SyncHourlyRecords] No active shift for today — skipped.', [
                'date' => $today,
            ]);

            return;
        }

        // ── 2. Load shift details with departments ───────────
        $shiftDetails = ShiftDetail::with('department')
            ->where('shift_id', $shift->id)
            ->get();

        if ($shiftDetails->isEmpty()) {
            return;
        }

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
                $synced += $this->syncDepartment($shift, $detail, $dept, $team);
            } catch (\Throwable $e) {
                Log::warning('[SyncHourlyRecords] Failed for department', [
                    'department' => $dept->code,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if ($synced > 0) {
            Log::info('[SyncHourlyRecords] Synced.', [
                'date'    => $today,
                'shift'   => $shift->shift_number,
                'records' => $synced,
            ]);
        }
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

        // Cache inventory per-department (fetched once, reused for all slots)
        $cachedInventory = null;

        $updated = 0;

        foreach ($records as $record) {
            // ── Parse hour_slot for full-hour query boundaries ──
            // "6h-7h" → queryStart=06:00, queryEnd=07:00
            [$queryStart, $queryEnd] = $this->parseHourSlot($record->hour_slot, $shiftDate);

            // ── Activation time: max(deptStart, queryStart) ─────
            // If dept starts at 06:30 and slot is "6h-7h",
            // the slot activates at 06:30 (not 06:00)
            $activationTime = $deptStart->gt($queryStart) ? $deptStart->copy() : $queryStart->copy();

            // ── Slot hasn't started yet → stay pending ───────
            if ($now < $activationTime) {
                continue;
            }

            // ── Already completed → skip ─────────────────────
            if ($record->status === HourlyRecordStatus::Completed->value) {
                continue;
            }

            $isCurrentSlot = $now >= $activationTime && $now < $queryEnd;
            $isPassedSlot = $now >= $queryEnd;

            if ($isCurrentSlot) {
                // ── Current slot: fetch data + set active ────
                $this->fetchAndUpdateRecord($record, $shiftDate, $team, $dept, $queryStart, $queryEnd, $cachedInventory);
                $updated++;
            } elseif ($isPassedSlot) {
                // ── Past slot: always fetch latest data ──────
                $this->fetchAndUpdateRecord($record, $shiftDate, $team, $dept, $queryStart, $queryEnd, $cachedInventory);

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
     * Sets: actual, staff, efficiency, status=active, hour_start_inventory (first time only).
     */
    private function fetchAndUpdateRecord(
        HourlyRecord $record,
        string $shiftDate,
        Team $team,
        Department $dept,
        Carbon $slotStart,
        Carbon $slotEnd,
        ?int &$cachedInventory,
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

        // ── Build update data ────────────────────────────────
        $updateData = [
            'actual'     => $actual,
            'staff'      => $staff,
            'efficiency' => $record->target > 0
                ? round(($actual / $record->target) * 100, 1)
                : 0,
            'status'     => HourlyRecordStatus::Active->value,
        ];

        // hour_start_inventory: chỉ set lần đầu (khi = 0 hoặc null)
        // Cache per-department: fetch 1 lần, dùng cho tất cả slots
        if ($record->hour_start_inventory === 0 || $record->hour_start_inventory === null) {
            if ($cachedInventory === null) {
                $cachedInventory = $this->fetchCurrentInventory($shiftDate, $team, $dept);
            }
            $updateData['hour_start_inventory'] = $cachedInventory;
        }

        $record->update($updateData);
    }

    /**
     * Lấy tồn hiện tại (ton_cuoi) từ FPlatform.
     * Dùng làm hour_start_inventory khi lần đầu sync hour slot.
     */
    private function fetchCurrentInventory(string $date, Team $team, Department $dept): int
    {
        try {
            $result = $this->inventoryAction->run($date, $team);

            return $result['ton_cuoi'] ?? 0;
        } catch (\Throwable $e) {
            Log::warning('[SyncHourlyRecords] Inventory query failed, using 0', [
                'department' => $dept->code,
                'error'      => $e->getMessage(),
            ]);

            return 0;
        }
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
}
