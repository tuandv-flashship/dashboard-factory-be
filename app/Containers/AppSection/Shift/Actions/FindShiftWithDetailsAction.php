<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Actions\Action as ParentAction;

final class FindShiftWithDetailsAction extends ParentAction
{
    public function run(int $id): Shift
    {
        return Shift::with([
            'template:id,name,color',
            'details.department.productionLine',
            'details.machines.machine',
            'details.latestChange',
            'hourlyRecords' => fn ($q) => $q->orderBy('department_id')->orderBy('hour_index'),
            'hourlyRecords.latestChange',
        ])->findOrFail($id);
    }
}
