<?php

namespace App\Containers\AppSection\Production\Jobs;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\FplatformData\Actions\GetHourlyMetricsAction;
use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
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
            }
        } catch (\Throwable $e) {
            Log::warning('[SyncDepartmentHourly] Failed', [
                'department' => $dept->code,
                'error'      => $e->getMessage(),
            ]);

            throw $e; // Let the batch handle the failure
        }
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
        $deptEnd = $deptStart->copy()->addMinutes((int) ($detail->work_hours * 60));

        $records = HourlyRecord::where('shift_id', $shift->id)
            ->where('department_id', $dept->id)
            ->orderBy('hour_index')
            ->get();

        $dayStartInventory = $this->refreshDayStartInventory($detail, $team, $allInventory);
        $breaks = $this->collectBreaks($detail);

        // Build slot map
        $deptWorkStart = Carbon::createFromFormat('H:i:s', $detail->start_time);
        $deptWorkEnd   = $deptWorkStart->copy()->addMinutes((int) ($detail->work_hours * 60));
        $alignedSlots  = $this->buildAlignedSlots($deptWorkStart, $deptWorkEnd);

        $slotMap = [];
        foreach ($alignedSlots as $i => $slot) {
            $slotMap[$i] = $slot;
        }

        // ── Pass 1: Recalculate kpi_hours ──
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

        // ── Pre-compute shared values ──
        $totalKpiHours = $records->sum('kpi_hours');
        $lastHourIndex = $records->max('hour_index');
        $isPerMachine  = $dept->productivity_type === ProductivityType::PerMachine;
        $kpiPerHour    = $isPerMachine ? ($detail->kpi_per_hour ?? 0) : ($dept->kpi_per_hour ?? 0);

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

        foreach ($records as $record) {
            [$queryStart, $queryEnd] = $this->parseHourSlot($record->hour_slot, $shiftDate);

            $activationTime = $deptStart->gt($queryStart) ? $deptStart->copy() : $queryStart->copy();
            $actualSlot = $slotMap[$record->hour_index] ?? null;
            $hourKey = $queryStart->format('Y-m-d H');

            if ($now < $activationTime) {
                continue;
            }

            $pastActual   = 0;
            $pastKpiHours = 0.0;
            foreach ($records as $r) {
                if ($r->hour_index >= $record->hour_index) {
                    break;
                }
                $pastActual   += (int) $r->actual;
                $pastKpiHours += (float) $r->kpi_hours;
            }

            $actual           = (int) ($productivityMap[$hourKey] ?? 0);
            $staff            = (int) ($staffCountMap[$hourKey] ?? 0);
            $productivityJson = $productivityDetailMap[$hourKey] ?? null;

            // ── Already completed → re-sync actual + recalc metrics ──
            if ($record->status === HourlyRecordStatus::Completed->value) {
                $remainingKpiHours = $totalKpiHours - $pastKpiHours;
                $hourStartInventory = max(0, $dayStartInventory - $pastActual);
                $staffRequired = $this->computeStaffRequired($dept, $hourStartInventory, $remainingKpiHours);

                $target = $this->computeTarget(
                    $staffRequired, $kpiPerHour, $record->kpi_hours,
                    $hourStartInventory, $record->hour_index, $lastHourIndex, $record->target
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
                $updated++;
                continue;
            }

            // ── Active / Pending slots ──
            $isCurrentSlot = $now >= $activationTime && $now < $queryEnd;
            $isPassedSlot  = $now >= $queryEnd;

            if ($isCurrentSlot || $isPassedSlot) {
                $this->updateRecord(
                    $record, $dept, $dayStartInventory,
                    $actual, $staff, $productivityJson,
                    $pastActual, $pastKpiHours, $totalKpiHours,
                    $kpiPerHour, $lastHourIndex,
                    $breaks, $actualSlot, $isPassedSlot,
                );
                $updated++;
            }
        }

        return $updated;
    }

    private function updateRecord(
        HourlyRecord $record,
        Department $dept,
        int $dayStartInventory,
        int $actual,
        int $staff,
        ?array $productivityJson,
        int $pastActual,
        float $pastKpiHours,
        float $totalKpiHours,
        float $kpiPerHour,
        int $lastHourIndex,
        array $breaks,
        ?array $actualSlot,
        bool $isCompleted,
    ): void {
        $hourStartInventory = max(0, $dayStartInventory - $pastActual);
        $remainingKpiHours  = $totalKpiHours - $pastKpiHours;
        $staffRequired      = $this->computeStaffRequired($dept, $hourStartInventory, $remainingKpiHours);

        $slotStart = $actualSlot ? $actualSlot['start'] : null;
        $slotEnd   = $actualSlot ? $actualSlot['end']   : null;

        $kpiHours = ($slotStart && $slotEnd)
            ? $this->computeKpiHours($slotStart, $slotEnd, $breaks)
            : $record->kpi_hours;

        $target = $this->computeTarget(
            $staffRequired, $kpiPerHour, $kpiHours,
            $hourStartInventory, $record->hour_index, $lastHourIndex, $record->target
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
            'efficiency'           => $target > 0
                ? round(($actual / $target) * 100, 1)
                : 0,
            'status'               => $status->value,
            'productivity_json'    => $productivityJson,
        ]);
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

    private function computeTarget(
        ?int $staffRequired, float $kpiPerHour, float $kpiHours,
        int $hourStartInventory, int $hourIndex, int $lastHourIndex, int $fallbackTarget,
    ): int {
        $target = ($staffRequired !== null && $staffRequired > 0)
            ? (int) round($staffRequired * $kpiPerHour * $kpiHours)
            : $fallbackTarget;

        if ($hourIndex === $lastHourIndex && $hourStartInventory < $target) {
            $target = $hourStartInventory;
        }

        return $target;
    }

    private function computeStaffRequired(Department $dept, int $inventory, float $remainingKpiHours): ?int
    {
        if ($dept->productivity_type === ProductivityType::PerMachine) {
            return 1;
        }

        $kpiPerHour = $dept->kpi_per_hour ?? 0;

        if ($inventory <= 0 || $remainingKpiHours <= 0 || $kpiPerHour <= 0) {
            return $inventory <= 0 ? 0 : null;
        }

        return (int) ceil($inventory / $remainingKpiHours / $kpiPerHour);
    }

    private function refreshDayStartInventory(ShiftDetail $detail, Team $team, array $allInventory): int
    {
        $teamData = $allInventory['teams'][$team->value] ?? null;
        $tongViec = (int) ($teamData['tong_viec'] ?? 0);

        if ($tongViec > 0 && $tongViec !== $detail->day_start_inventory) {
            $detail->update(['day_start_inventory' => $tongViec]);
        }

        return $tongViec > 0 ? $tongViec : $detail->day_start_inventory;
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

    private function computeKpiHoursFromLabel(string $hourSlot, string $shiftDate, array $breaks): float
    {
        [$start, $end] = $this->parseHourSlot($hourSlot, $shiftDate);
        return $this->computeKpiHours($start, $end, $breaks);
    }
}
