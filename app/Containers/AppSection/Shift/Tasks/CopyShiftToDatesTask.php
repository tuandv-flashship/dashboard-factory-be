<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftDetailMachine;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;

/**
 * Copy a shift (header + details + hourly_records) to target dates.
 * Returns ['created' => [...dates], 'skipped' => [...{date, reason}]].
 *
 * Optimized: bulk insert for details and hourly_records instead of N+1 queries.
 */
final class CopyShiftToDatesTask extends ParentTask
{
    public function run(Shift $source, array $targetDates): array
    {
        $created = [];
        $skipped = [];
        $today = today()->toDateString();

        // Pre-load source data once (not per-date)
        $sourceDetails = ShiftDetail::where('shift_id', $source->id)->get();
        $sourceHourly  = HourlyRecord::where('shift_id', $source->id)->get();

        // Pre-load machine pivot records keyed by source shift_detail_id
        $sourceMachines = ShiftDetailMachine::whereIn(
            'shift_detail_id', $sourceDetails->pluck('id')
        )->get()->groupBy('shift_detail_id');

        foreach ($targetDates as $date) {
            // Validate: target date >= today
            if ($date < $today) {
                $skipped[] = [
                    'date'   => $date,
                    'reason' => 'past_date',
                ];
                continue;
            }

            // Check if shift already exists for this date + shift_number
            $exists = Shift::where('date', $date)
                ->where('shift_number', $source->shift_number)
                ->exists();

            if ($exists) {
                $skipped[] = [
                    'date'   => $date,
                    'reason' => 'already_exists',
                ];
                continue;
            }

            // Clone shift header
            $newShift = Shift::create([
                'date'              => $date,
                'shift_number'      => $source->shift_number,
                'start_time'        => $source->start_time,
                'end_time'          => $source->end_time,
                'supervisor'        => $source->supervisor,
                'is_active'         => true,
                'shift_template_id' => $source->shift_template_id,
            ]);

            // Bulk insert details
            $now = now();
            $detailRows = $sourceDetails->map(fn (ShiftDetail $detail) => [
                'shift_id'           => $newShift->id,
                'department_id'      => $detail->department_id,
                'shift_number'       => $detail->shift_number,
                'headcount'          => $detail->headcount,
                'kpi_per_hour'       => $detail->kpi_per_hour,
                'day_start_inventory'=> $detail->day_start_inventory,
                'start_time'         => $detail->start_time,
                'work_hours'         => $detail->work_hours,
                'prep_minutes'       => $detail->prep_minutes,
                'break1_start'       => $detail->break1_start,
                'break1_minutes'     => $detail->break1_minutes,
                'meal_break_start'   => $detail->meal_break_start,
                'meal_break_minutes' => $detail->meal_break_minutes,
                'break2_start'       => $detail->break2_start,
                'break2_minutes'     => $detail->break2_minutes,
                'break3_start'       => $detail->break3_start,
                'break3_minutes'     => $detail->break3_minutes,
                'created_at'         => $now,
                'updated_at'         => $now,
            ])->toArray();

            if (!empty($detailRows)) {
                ShiftDetail::insert($detailRows);
            }

            // Copy shift_detail_machines pivot (for per_machine departments)
            $this->copyMachines($sourceDetails, $newShift, $sourceMachines, $now);

            // Bulk insert hourly records (skeleton only — no actual data)
            $hourlyRows = $sourceHourly->map(fn (HourlyRecord $hr) => [
                'shift_id'             => $newShift->id,
                'department_id'        => $hr->department_id,
                'hour_slot'            => $hr->hour_slot,
                'hour_index'           => $hr->hour_index,
                'staff'                => $hr->staff,
                'hour_start_inventory' => 0,
                'target'               => $hr->target,
                'actual'               => null,
                'efficiency'           => 0,
                'error_rate'           => 0,
                'status'               => HourlyRecordStatus::Pending->value,
                'created_at'           => $now,
                'updated_at'           => $now,
            ])->toArray();

            if (!empty($hourlyRows)) {
                HourlyRecord::insert($hourlyRows);
            }

            $created[] = $date;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * Copy shift_detail_machines pivot records from source to new shift.
     */
    private function copyMachines(
        \Illuminate\Database\Eloquent\Collection $sourceDetails,
        Shift $newShift,
        \Illuminate\Support\Collection $sourceMachines,
        \DateTimeInterface $now,
    ): void {
        if ($sourceMachines->isEmpty()) {
            return;
        }

        // Map source detail IDs to new detail IDs via (department_id, shift_number) key
        $newDetails = ShiftDetail::where('shift_id', $newShift->id)
            ->get()
            ->keyBy(fn ($d) => "{$d->department_id}|{$d->shift_number}");

        $pivotRows = [];

        foreach ($sourceDetails as $srcDetail) {
            $machines = $sourceMachines->get($srcDetail->id);
            if (!$machines || $machines->isEmpty()) {
                continue;
            }

            $key = "{$srcDetail->department_id}|{$srcDetail->shift_number}";
            $newDetail = $newDetails->get($key);
            if (!$newDetail) {
                continue;
            }

            foreach ($machines as $m) {
                $pivotRows[] = [
                    'shift_detail_id' => $newDetail->id,
                    'machine_id'      => $m->machine_id,
                    'kpi_per_hour'    => $m->kpi_per_hour,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
        }

        if (!empty($pivotRows)) {
            ShiftDetailMachine::insert($pivotRows);
        }
    }
}
