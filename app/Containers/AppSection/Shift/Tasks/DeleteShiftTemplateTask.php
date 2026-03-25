<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class DeleteShiftTemplateTask extends ParentTask
{
    public function run(ShiftTemplate $template): void
    {
        $template->delete();
    }
}
