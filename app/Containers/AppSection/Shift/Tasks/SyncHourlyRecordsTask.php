<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;

/**
 * Smart-sync hourly_records after shift_details have been updated.
 *
 * – Preserves actual, hour_start_inventory, efficiency, error_rate
 *   for records that still match the new shift configuration.
 * – Soft-deletes records that no longer match (e.g. work_hours reduced,
 *   department removed) so historical data is retained.
 * – Restores previously soft-deleted records if a department/hour_index
 *   comes back into scope.
 * – Loads only required departments via whereIn (no Department::all()).
 *
 * Per-machine target: shift_detail.kpi_per_hour (NOT × headcount).
 * Per-person  target: department.kpi_per_hour × headcount.
 */
final class SyncHourlyRecordsTask extends ParentTask
{
    public function run(Shift $shift): void
    {
        // ── 1. Snapshot existing records (including soft-deleted) ──
        $existing = HourlyRecord::withTrashed()
            ->where('shift_id', $shift->id)
            ->get()
            ->keyBy(fn ($r) => "{$r->department_id}_{$r->hour_index}");

        // ── 2. Load only required departments ──
        $shiftDetails = ShiftDetail::where('shift_id', $shift->id)->get();
        $deptIds = $shiftDetails->pluck('department_id')->unique();
        $departments = Department::whereIn('id', $deptIds)->get()->keyBy('id');

        // ── 3. Compute new record set ──
        $newKeys = [];
        $records = [];
        $deptHourIndex = [];
        $now = now();

        foreach ($shiftDetails as $detail) {
            $deptId     = $detail->department_id;
            $dept       = $departments->get($deptId);
            $isPerMachine = $dept?->productivity_type === ProductivityType::PerMachine;

            // Per-machine: target = shift_detail.kpi_per_hour (Σ machine KPIs)
            // Per-person:  target = department.kpi_per_hour × headcount
            if ($isPerMachine) {
                $target = $detail->kpi_per_hour ?? 0;
            } else {
                $kpiPerHour = $dept?->kpi_per_hour ?? 0;
                $target = (int) round($kpiPerHour * $detail->headcount);
            }

            $hours      = (int) floor($detail->work_hours);
            $start      = Carbon::createFromFormat('H:i:s', $detail->start_time);

            for ($i = 0; $i < $hours; $i++) {
                $deptHourIndex[$deptId] = ($deptHourIndex[$deptId] ?? -1) + 1;
                $idx = $deptHourIndex[$deptId];
                $key = "{$deptId}_{$idx}";
                $newKeys[] = $key;

                $slotStart = $start->copy()->addHours($i);
                $slotEnd   = $slotStart->copy()->addHour();
                $hourSlot  = $slotStart->format('G') . 'h-' . $slotEnd->format('G') . 'h';

                $prev = $existing->get($key);

                $records[$key] = [
                    'shift_id'             => $shift->id,
                    'department_id'        => $deptId,
                    'hour_slot'            => $hourSlot,
                    'hour_index'           => $idx,
                    'staff'                => $detail->headcount,
                    'target'               => $target,
                    // ── Preserve actual data when it exists ──
                    'actual'               => $prev?->actual,
                    'hour_start_inventory' => $prev?->hour_start_inventory ?? 0,
                    'efficiency'           => $prev?->efficiency ?? 0,
                    'error_rate'           => $prev?->error_rate ?? 0,
                    'status'               => $prev?->status ?? HourlyRecordStatus::Pending->value,
                    'deleted_at'           => null, // restore if previously soft-deleted
                    'created_at'           => $prev?->created_at ?? $now,
                    'updated_at'           => $now,
                ];
            }
        }

        // ── 4. Soft-delete records no longer in the new set ──
        $existing->each(function ($record) use ($newKeys) {
            $key = "{$record->department_id}_{$record->hour_index}";
            if (!in_array($key, $newKeys, true) && $record->deleted_at === null) {
                $record->delete(); // soft delete — preserves historical data
            }
        });

        // ── 5. Upsert all records (insert new, update existing, restore soft-deleted) ──
        if (!empty($records)) {
            HourlyRecord::withTrashed()->upsert(
                array_values($records),
                ['shift_id', 'department_id', 'hour_index'],
                [
                    'hour_slot', 'staff', 'target', 'actual',
                    'hour_start_inventory', 'efficiency', 'error_rate',
                    'status', 'deleted_at', 'updated_at',
                ]
            );
        }
    }
}
