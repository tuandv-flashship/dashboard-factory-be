<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Actions\Action as ParentAction;
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

        // Cascade: shift_details + hourly_records deleted via FK constraints
        $shift->delete();
    }
}
