<?php

namespace App\Containers\AppSection\Shift\Traits;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Recalculate Shift header start/end times from all ShiftDetails.
 *
 * Mirrors CreateShiftAction logic:
 *   start_time = min(details.start_time)
 *   end_time   = max(details.end_time)  // accessor: start + work_hours + meal_break
 *
 * Used by: UpdateShiftAction, UpdateShiftDepartmentAction.
 */
trait RecalculatesShiftTimes
{
    private function recalculateShiftEndTime(Shift $shift): void
    {
        $details = ShiftDetail::where('shift_id', $shift->id)->get();

        if ($details->isEmpty()) {
            return;
        }

        $minStart = $details->min('start_time');
        $maxEnd   = $details->max(fn (ShiftDetail $d) => $d->end_time);

        $update = [];

        if ($minStart !== null) {
            $formatted = Carbon::createFromFormat(
                substr_count($minStart, ':') === 2 ? 'H:i:s' : 'H:i',
                $minStart
            )->format('H:i');

            $currentStart = Carbon::createFromFormat(
                substr_count($shift->start_time, ':') === 2 ? 'H:i:s' : 'H:i',
                $shift->start_time
            )->format('H:i');

            if ($formatted !== $currentStart) {
                $update['start_time'] = $formatted;
            }
        }

        if ($maxEnd !== null) {
            $currentEnd = Carbon::createFromFormat(
                substr_count($shift->end_time, ':') === 2 ? 'H:i:s' : 'H:i',
                $shift->end_time
            )->format('H:i');

            if ($maxEnd !== $currentEnd) {
                $update['end_time'] = $maxEnd;
            }
        }

        if (!empty($update)) {
            $shift->update($update);
            Log::info('[RecalculatesShiftTimes] Updated shift header times.', [
                'shift_id' => $shift->id,
                ...$update,
            ]);
        }
    }
}
