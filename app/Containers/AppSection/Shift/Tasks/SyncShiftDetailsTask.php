<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Sync (delete + recreate) shift_details for a given shift.
 */
final class SyncShiftDetailsTask extends ParentTask
{
    public function run(Shift $shift, array $detailsData): void
    {
        // Delete existing details
        ShiftDetail::where('shift_id', $shift->id)->delete();

        // Create new details
        foreach ($detailsData as $detail) {
            ShiftDetail::create(array_merge($detail, [
                'shift_id' => $shift->id,
            ]));
        }
    }
}
