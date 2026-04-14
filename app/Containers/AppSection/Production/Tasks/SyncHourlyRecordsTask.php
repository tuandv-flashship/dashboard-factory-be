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
 * For the active shift's current hour slot, updates:
 * - hour_start_inventory (only first time, via FPlatform tồn cuối query)
 * - actual (productivity from FPlatform)
 * - staff (staff_count from FPlatform)
 * - efficiency (computed: actual / target * 100)
 * - status: pending → active
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

        if (!$shift || $shift->date->toDateString() !== $today) {
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
                $updated = $this->syncDepartment($shift, $detail, $dept, $team);
                if ($updated) {
                    $synced++;
                }
            } catch (\Throwable $e) {
                Log::warning('[SyncHourlyRecords] Failed for department', [
                    'department' => $dept->code,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if ($synced > 0) {
            Log::info('[SyncHourlyRecords] Synced.', [
                'date'     => $today,
                'shift'    => $shift->shift_number,
                'departments' => $synced,
            ]);
        }
    }

    /**
     * Sync a single department's current hour slot.
     */
    private function syncDepartment(
        Shift $shift,
        ShiftDetail $detail,
        Department $dept,
        Team $team,
    ): bool {
        // ── Determine current hour slot ──────────────────────
        $now = now();
        $shiftDate = $shift->date->toDateString();

        $startTime = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $shiftDate . ' ' . $detail->start_time
        );

        $workHours = (int) floor($detail->work_hours);
        $currentHourIndex = null;
        $slotStart = null;
        $slotEnd = null;

        for ($i = 0; $i < $workHours; $i++) {
            $hourStart = $startTime->copy()->addHours($i);
            $hourEnd = $hourStart->copy()->addHour();

            if ($now >= $hourStart && $now < $hourEnd) {
                $currentHourIndex = $i;
                $slotStart = $hourStart;
                $slotEnd = $hourEnd;
                break;
            }
        }

        if ($currentHourIndex === null) {
            // Not currently in any hour slot for this department
            return false;
        }

        // ── Find hourly_record ───────────────────────────────
        $record = HourlyRecord::where('shift_id', $shift->id)
            ->where('department_id', $dept->id)
            ->where('hour_index', $currentHourIndex)
            ->first();

        if (!$record) {
            return false;
        }

        // ── Convert to US/Central for FPlatform queries ──────
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

        // hour_start_inventory: chỉ set lần đầu (khi = 0)
        // Lấy ton_cuoi tại thời điểm query = tồn hiện tại = tồn đầu giờ
        if ($record->hour_start_inventory === 0 || $record->hour_start_inventory === null) {
            $updateData['hour_start_inventory'] = $this->fetchCurrentInventory($shiftDate, $team, $dept);
        }

        $record->update($updateData);

        return true;
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
}
