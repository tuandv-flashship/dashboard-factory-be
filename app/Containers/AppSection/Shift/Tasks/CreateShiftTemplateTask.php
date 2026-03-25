<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class CreateShiftTemplateTask extends ParentTask
{
    public function run(array $data): ShiftTemplate
    {
        return ShiftTemplate::create($data);
    }
}
