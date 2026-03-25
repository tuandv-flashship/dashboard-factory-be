<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class UpdateShiftTemplateTask extends ParentTask
{
    public function run(ShiftTemplate $template, array $data): ShiftTemplate
    {
        $template->update($data);

        return $template;
    }
}
