<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class FindShiftTemplateByIdTask extends ParentTask
{
    public function run(int $id): ShiftTemplate
    {
        return ShiftTemplate::with([
            'details' => fn ($q) => $q->whereHas('department', fn ($d) => $d->where('is_hidden', false)),
            'details.department.productionLine',
            'details.department.machines',
        ])->findOrFail($id);
    }
}
