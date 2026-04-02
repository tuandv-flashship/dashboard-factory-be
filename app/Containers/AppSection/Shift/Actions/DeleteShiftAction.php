<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteShiftAction extends ParentAction
{
    public function run(int $id): void
    {
        $shift = Shift::findOrFail($id);

        // Business rule: cannot delete past shifts
        if ($shift->date->lt(today())) {
            throw ValidationException::withMessages([
                'date' => ['Không thể xóa ca của ngày đã qua.'],
            ]);
        }

        DB::transaction(function () use ($shift) {
            // Soft-delete hourly_records first (preserve historical data).
            // Must be done manually because DB-level cascadeOnDelete
            // would hard-delete and bypass Eloquent SoftDeletes.
            HourlyRecord::where('shift_id', $shift->id)->delete();

            // Hard-delete shift (cascades to shift_details via FK)
            $shift->delete();
        });
    }
}
