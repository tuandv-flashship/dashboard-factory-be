<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftDetailMachine;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Copy shift_template_details → shift_details for a given shift.
 *
 * Chỉ copy các details khớp với shift_number của shift đang tạo.
 * Nếu FE gửi kèm $overrides (details đã chỉnh sửa từ mockup),
 * thì merge override vào trước khi lưu.
 *
 * Override được key theo "department_id|shift_number".
 *
 * Per-machine departments (e.g. DTG Print):
 *   - FE gửi machine_ids → server lookup KPI từng máy
 *   - kpi_per_hour = Σ(machine.kpi_per_hour) — tổng KPI máy được chọn
 *   - Tạo pivot records shift_detail_machines với snapshot KPI
 *
 * Optimized: bulk insert instead of N separate create() calls.
 */
final class CreateShiftFromTemplateTask extends ParentTask
{
    public function run(Shift $shift, int $templateId, array $overrides = []): void
    {
        $templateDetails = ShiftTemplateDetail::with('department')
            ->where('shift_template_id', $templateId)
            ->where('shift_number', $shift->shift_number)
            ->get();

        if ($templateDetails->isEmpty()) {
            return;
        }

        // Index overrides by "department_id|shift_number" for O(1) lookup
        $overrideMap = collect($overrides)->keyBy(
            fn ($o) => "{$o['department_id']}|{$o['shift_number']}"
        );

        $now = now();

        $rows = $templateDetails->map(function ($td) use ($shift, $overrideMap, $now) {
            $key      = "{$td->department_id}|{$td->shift_number}";
            $override = $overrideMap->get($key, []);

            // Per-machine: kpi_per_hour will be computed after insert (from machine_ids)
            // Per-person: snapshot from department as before
            $isPerMachine = $td->department?->productivity_type === ProductivityType::PerMachine;
            $kpiPerHour   = $isPerMachine ? 0 : ($td->department?->kpi_per_hour ?? 0);

            return [
                'shift_id'           => $shift->id,
                'department_id'      => $td->department_id,
                'shift_number'       => $td->shift_number,
                // headcount: KHÔNG cho FE override — luôn copy từ template (cell màu vàng, read-only)
                'headcount'          => $td->headcount,
                // Snapshot năng suất 1h — per_machine = 0 initially (updated below)
                'kpi_per_hour'       => $kpiPerHour,
                // Tồn đầu ngày — FE gửi kèm, mặc định 0
                'day_start_inventory'=> $override['day_start_inventory'] ?? 0,
                'start_time'         => $override['start_time']         ?? $td->start_time,
                'work_hours'         => $override['work_hours']         ?? $td->work_hours,
                'prep_minutes'       => $override['prep_minutes']       ?? $td->prep_minutes,
                'break1_start'       => $override['break1_start']       ?? $td->break1_start,
                'break1_minutes'     => $override['break1_minutes']     ?? $td->break1_minutes,
                'meal_break_start'   => $override['meal_break_start']   ?? $td->meal_break_start,
                'meal_break_minutes' => $override['meal_break_minutes'] ?? $td->meal_break_minutes,
                'break2_start'       => $override['break2_start']       ?? $td->break2_start,
                'break2_minutes'     => $override['break2_minutes']     ?? $td->break2_minutes,
                'break3_start'       => $override['break3_start']       ?? $td->break3_start,
                'break3_minutes'     => $override['break3_minutes']     ?? $td->break3_minutes,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        })->toArray();

        ShiftDetail::insert($rows);

        // ── Per-machine: attach machines & update kpi_per_hour ──
        $this->attachMachines($shift, $templateDetails, $overrideMap, $now);
    }

    /**
     * For per_machine departments: create shift_detail_machines pivot + update kpi_per_hour.
     */
    private function attachMachines(
        Shift $shift,
        \Illuminate\Support\Collection $templateDetails,
        \Illuminate\Support\Collection $overrideMap,
        \DateTimeInterface $now,
    ): void {
        // Collect per_machine departments
        $perMachineDepts = $templateDetails->filter(
            fn ($td) => $td->department?->productivity_type === ProductivityType::PerMachine
        );

        if ($perMachineDepts->isEmpty()) {
            return;
        }

        // Collect ALL machine_ids from all per_machine overrides for batch query
        $allMachineIds = [];
        foreach ($perMachineDepts as $td) {
            $key      = "{$td->department_id}|{$td->shift_number}";
            $override = $overrideMap->get($key, []);
            $machineIds = $override['machine_ids'] ?? [];
            if (!empty($machineIds)) {
                $allMachineIds = array_merge($allMachineIds, $machineIds);
            }
        }

        if (empty($allMachineIds)) {
            return; // No machines selected for any per_machine dept
        }

        // Single batch query: load all selected machines at once
        $allMachines = Machine::whereIn('id', array_unique($allMachineIds))->get()->keyBy('id');

        // Re-fetch the newly created shift_details for these departments
        $deptIds = $perMachineDepts->pluck('department_id')->unique()->toArray();
        $shiftDetails = ShiftDetail::where('shift_id', $shift->id)
            ->whereIn('department_id', $deptIds)
            ->get()
            ->keyBy(fn ($sd) => "{$sd->department_id}|{$sd->shift_number}");

        $pivotRows = [];

        foreach ($perMachineDepts as $td) {
            $key      = "{$td->department_id}|{$td->shift_number}";
            $override = $overrideMap->get($key, []);

            $machineIds = $override['machine_ids'] ?? [];
            if (empty($machineIds)) {
                continue; // No machines selected → kpi stays 0
            }

            $shiftDetail = $shiftDetails->get($key);
            if (!$shiftDetail) {
                continue;
            }

            // Filter from pre-loaded collection (safety: only machines from this dept)
            $totalKpi = 0;
            foreach ($machineIds as $machineId) {
                $machine = $allMachines->get($machineId);
                if (!$machine || $machine->department_id !== $td->department_id) {
                    continue; // Skip invalid: wrong dept or non-existent
                }
                $pivotRows[] = [
                    'shift_detail_id' => $shiftDetail->id,
                    'machine_id'      => $machine->id,
                    'kpi_per_hour'    => $machine->kpi_per_hour,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
                $totalKpi += $machine->kpi_per_hour;
            }

            // Update shift_detail.kpi_per_hour = Σ(machine KPI)
            $shiftDetail->update(['kpi_per_hour' => $totalKpi]);
        }

        if (!empty($pivotRows)) {
            ShiftDetailMachine::insert($pivotRows);
        }
    }
}
