<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Traits\ComputesHourlyTarget;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;

/**
 * Smart-sync hourly_records after shift_details have been updated.
 *
 * – Preserves actual, hour_start_inventory, efficiency, error_rate
 * – Soft-deletes stale records via bulk query (not N loops)
 * – Restores previously soft-deleted records via upsert
 * – Target calculation via ComputesHourlyTarget trait
 */
final class SyncHourlyRecordsTask extends ParentTask
{
    use ComputesHourlyTarget;

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

        // ── 3. Compute new record set ──
        $newKeys       = [];
        $records       = [];
        $deptHourIndex = [];
        $now           = now();

        foreach ($shiftDetails as $detail) {
            $deptId = $detail->department_id;
            $dept   = $departments->get($deptId);
            $target = $this->computeTarget($dept, $detail, $detail->headcount);

            $hours = (int) floor($detail->work_hours);
            $start = Carbon::createFromFormat('H:i:s', $detail->start_time);

            for ($i = 0; $i < $hours; $i++) {
                $deptHourIndex[$deptId] = ($deptHourIndex[$deptId] ?? -1) + 1;
                $idx = $deptHourIndex[$deptId];
                $key = "{$deptId}_{$idx}";
                $newKeys[] = $key;

                $slotStart = $start->copy()->addHours($i);
                $slotEnd   = $slotStart->copy()->addHour();

                $prev = $existing->get($key);

                $records[$key] = [
                    'shift_id'             => $shift->id,
                    'department_id'        => $deptId,
                    'hour_slot'            => $slotStart->format('G') . 'h-' . $slotEnd->format('G') . 'h',
                    'hour_index'           => $idx,
                    'staff'                => $detail->headcount,
                    'target'               => $target,
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
                    'hour_start_inventory', 'efficiency', 'error_rate',
                    'status', 'productivity_json', 'deleted_at', 'updated_at',
                ]
            );
        }
    }
}
