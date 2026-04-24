<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Traits\ComputesHourlyTarget;
use App\Containers\AppSection\Shift\Traits\ComputesKpiHours;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;

/**
 * Smart-sync hourly_records after shift_details have been updated.
 *
 * Uses the same aligned-slot logic as GenerateHourlyRecordsTask:
 * – Partial first/last slots for departments starting/ending mid-hour
 * – kpi_hours, kpi_minutes, kpi_percent computed per slot
 * – Target prorated by kpi_percent for partial slots
 *
 * – Preserves actual, hour_start_inventory, efficiency, error_rate
 * – Soft-deletes stale records via bulk query (not N loops)
 * – Restores previously soft-deleted records via upsert
 * – Target calculation via ComputesHourlyTarget trait
 */
final class SyncHourlyRecordsTask extends ParentTask
{
    use ComputesHourlyTarget;
    use ComputesKpiHours;

    public function run(Shift $shift): void
    {
        // ── 1. Snapshot existing records (including soft-deleted) ──
        $existing = HourlyRecord::withTrashed()
            ->where('shift_id', $shift->id)
            ->get()
            ->keyBy(fn ($r) => "{$r->department_id}_{$r->hour_index}");

        // ── 2. Load shift_details + departments ──
        $shiftDetails = ShiftDetail::where('shift_id', $shift->id)->get();
        $departments  = Department::whereIn('id', $shiftDetails->pluck('department_id')->unique())
            ->get()
            ->keyBy('id');

        // ── 3. Compute new record set (aligned slots) ──
        $newKeys = [];
        $records = [];
        $now     = now();

        foreach ($shiftDetails as $detail) {
            $deptId         = $detail->department_id;
            $dept           = $departments->get($deptId);
            $fullHourTarget = $this->computeTarget($dept, $detail, $detail->headcount);

            $start  = Carbon::createFromFormat('H:i:s', $detail->start_time);
            $end    = $start->copy()->addMinutes((int) ($detail->work_hours * 60) + ($detail->meal_break_minutes ?? 0));
            $breaks = $this->collectBreaks($detail, $detail->start_time);
            $slots  = $this->buildAlignedSlots($start, $end);

            $hourIndex = 0;

            foreach ($slots as $slot) {
                $key       = "{$deptId}_{$hourIndex}";
                $newKeys[] = $key;
                $prev      = $existing->get($key);

                $kpiData = $this->computeKpiHoursData($slot['start'], $slot['end'], $breaks);

                $records[$key] = [
                    'shift_id'             => $shift->id,
                    'department_id'        => $deptId,
                    'hour_slot'            => $slot['label'],
                    'hour_index'           => $hourIndex,
                    'staff'                => $detail->headcount,
                    'target'               => (int) round($fullHourTarget * $kpiData['percent'] / 100),
                    'kpi_hours'            => $kpiData['hours'],
                    'kpi_minutes'          => $kpiData['minutes'],
                    'kpi_percent'          => $kpiData['percent'],
                    // ── Preserve actual data when it exists ──
                    'actual'               => $prev?->actual,
                    'hour_start_inventory' => $prev?->hour_start_inventory ?? 0,
                    'efficiency'           => $prev?->efficiency ?? 0,
                    'error_rate'           => $prev?->error_rate ?? 0,
                    'status'               => $prev?->status ?? HourlyRecordStatus::Pending->value,
                    'productivity_json'    => $prev?->productivity_json
                        ? json_encode($prev->productivity_json)
                        : null,
                    'deleted_at'           => null, // restore if previously soft-deleted
                    'created_at'           => $prev?->created_at ?? $now,
                    'updated_at'           => $now,
                ];

                $hourIndex++;
            }
        }

        // ── 4. Bulk soft-delete stale records (1 query instead of N) ──
        $staleIds = $existing
            ->filter(fn ($r, $key) => !in_array($key, $newKeys, true) && $r->deleted_at === null)
            ->pluck('id')
            ->toArray();

        if (!empty($staleIds)) {
            HourlyRecord::whereIn('id', $staleIds)->update(['deleted_at' => $now]);
        }

        // ── 5. Upsert all records (insert new, update existing, restore soft-deleted) ──
        if (!empty($records)) {
            HourlyRecord::withTrashed()->upsert(
                array_values($records),
                ['shift_id', 'department_id', 'hour_index'],
                [
                    'hour_slot', 'staff', 'target', 'actual',
                    'kpi_hours', 'kpi_minutes', 'kpi_percent',
                    'hour_start_inventory', 'efficiency', 'error_rate',
                    'status', 'productivity_json', 'deleted_at', 'updated_at',
                ]
            );
        }
    }
}
