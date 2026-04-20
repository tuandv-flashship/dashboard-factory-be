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
 * Generate hourly_records from shift_details.
 *
 * Slots are aligned to full-hour boundaries:
 * - If a department starts at :30, the first slot is a partial half-hour
 * - Middle slots are full hours
 * - If a department ends at :30, the last slot is a partial half-hour
 * - Target is prorated for partial slots
 * - kpi_hours = slot_duration - break overlap (effective KPI time)
 *
 * Example: print (06:30, 8h work):
 *   slot 0: "6h-7h"   06:30–07:00  target=½×KPI  kpi_hours=0.50
 *   slot 1: "7h-8h"   07:00–08:00  target=1×KPI  kpi_hours=1.00
 *   ...
 *   slot 4: "10h-11h" 10:00–11:00  break1=15min  kpi_hours=0.75
 *   ...
 *   slot 8: "14h-15h" 14:00–14:30  target=½×KPI  kpi_hours=0.50
 *
 * Optimized: single bulk insert, departments loaded via whereIn.
 * Target calculation delegated to ComputesHourlyTarget trait.
 * Slot building and KPI hours delegated to ComputesKpiHours trait.
 */
final class GenerateHourlyRecordsTask extends ParentTask
{
    use ComputesHourlyTarget;
    use ComputesKpiHours;

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

            $breaks = $this->collectBreaks($detail, $detail->start_time);
            $slots = $this->buildAlignedSlots($start, $end);
            $hourIndex = 0;

            foreach ($slots as $slot) {
                $kpiData  = $this->computeKpiHoursData($slot['start'], $slot['end'], $breaks);

                $records[] = [
                    'shift_id'             => $shift->id,
                    'department_id'        => $deptId,
                    'hour_slot'            => $slot['label'],
                    'hour_index'           => $hourIndex,
                    'staff'                => $detail->headcount,
                    'hour_start_inventory' => 0,
                    'target'               => (int) round($fullHourTarget * $slot['fraction']),
                    'kpi_hours'            => $kpiData['hours'],
                    'kpi_minutes'          => $kpiData['minutes'],
                    'kpi_percent'          => $kpiData['percent'],
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
}
