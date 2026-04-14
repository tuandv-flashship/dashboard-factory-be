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
 * Generate hourly_records from shift_details.
 *
 * Slots are aligned to full-hour boundaries:
 * - If a department starts at :30, the first slot is a partial half-hour
 * - Middle slots are full hours
 * - If a department ends at :30, the last slot is a partial half-hour
 * - Target is prorated for partial slots
 *
 * Example: print (06:30, 8h work):
 *   slot 0: "6h-7h"   06:30–07:00  target=½×KPI
 *   slot 1: "7h-8h"   07:00–08:00  target=1×KPI
 *   ...
 *   slot 8: "14h-15h" 14:00–14:30  target=½×KPI
 *
 * Optimized: single bulk insert, departments loaded via whereIn.
 * Target calculation delegated to ComputesHourlyTarget trait.
 */
final class GenerateHourlyRecordsTask extends ParentTask
{
    use ComputesHourlyTarget;

    public function run(Shift $shift): void
    {
        $shiftDetails = ShiftDetail::where('shift_id', $shift->id)->get();

        if ($shiftDetails->isEmpty()) {
            return;
        }

        $departments = Department::whereIn('id', $shiftDetails->pluck('department_id')->unique())
            ->get()
            ->keyBy('id');

        $records = [];
        $now = now();

        foreach ($shiftDetails as $detail) {
            $deptId = $detail->department_id;
            $dept   = $departments->get($deptId);
            $fullHourTarget = $this->computeTarget($dept, $detail, $detail->headcount);

            $start = Carbon::createFromFormat('H:i:s', $detail->start_time);
            $end = $start->copy()->addMinutes((int) ($detail->work_hours * 60));

            // Build full-hour-aligned slots
            $slots = $this->buildAlignedSlots($start, $end);
            $hourIndex = 0;

            foreach ($slots as $slot) {
                // Prorate target for partial slots
                $target = (int) round($fullHourTarget * $slot['fraction']);

                $records[] = [
                    'shift_id'             => $shift->id,
                    'department_id'        => $deptId,
                    'hour_slot'            => $slot['label'],
                    'hour_index'           => $hourIndex,
                    'staff'                => $detail->headcount,
                    'hour_start_inventory' => 0,
                    'target'               => $target,
                    'actual'               => null,
                    'efficiency'           => 0,
                    'error_rate'           => 0,
                    'status'               => HourlyRecordStatus::Pending->value,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];

                $hourIndex++;
            }
        }

        if (!empty($records)) {
            HourlyRecord::insert($records);
        }
    }

    /**
     * Build full-hour-aligned slots between start and end times.
     *
     * Returns array of slots, each with:
     * - label: "6h-7h" format
     * - fraction: 0.0–1.0 (portion of a full hour)
     *
     * @return array<array{label: string, fraction: float}>
     */
    private function buildAlignedSlots(Carbon $start, Carbon $end): array
    {
        $slots = [];

        // First full-hour boundary at or after start
        $firstFullHour = $start->copy()->startOfHour();
        if ($firstFullHour->lt($start)) {
            $firstFullHour->addHour();
        }

        // Last full-hour boundary at or before end
        $lastFullHour = $end->copy()->startOfHour();

        // ── Partial first slot (if start is not on the hour) ──
        if ($start->minute > 0 && $firstFullHour->lte($end)) {
            $slotEnd = $firstFullHour->copy()->min($end);
            $minutes = $start->diffInMinutes($slotEnd);

            $slots[] = [
                'label'    => $start->format('G') . 'h-' . $slotEnd->format('G') . 'h',
                'fraction' => round($minutes / 60, 2),
            ];
        }

        // ── Full-hour slots ──────────────────────────────────
        $cursor = $firstFullHour->copy();
        while ($cursor->lt($lastFullHour)) {
            $slotEnd = $cursor->copy()->addHour();

            $slots[] = [
                'label'    => $cursor->format('G') . 'h-' . $slotEnd->format('G') . 'h',
                'fraction' => 1.0,
            ];

            $cursor->addHour();
        }

        // ── Partial last slot (if end is not on the hour) ────
        if ($end->minute > 0 && $lastFullHour->gte($firstFullHour)) {
            $minutes = $lastFullHour->diffInMinutes($end);

            $slots[] = [
                'label'    => $lastFullHour->format('G') . 'h-' . $end->copy()->startOfHour()->addHour()->format('G') . 'h',
                'fraction' => round($minutes / 60, 2),
            ];
        }

        return $slots;
    }
}
