<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class CopyShiftTemplateTask extends ParentTask
{
    /**
     * Clone a shift template and its details.
     */
    public function run(ShiftTemplate $source): ShiftTemplate
    {
        $clone = $source->replicate(['id']);
        $clone->name = $source->name . ' (Copy)';
        $clone->sort_order = ShiftTemplate::max('sort_order') + 1;
        $clone->save();

        foreach ($source->details as $detail) {
            $newDetail = $detail->replicate(['id', 'shift_template_id']);
            $newDetail->shift_template_id = $clone->id;
            $newDetail->save();
        }

        return $clone->load('details.department');
    }
}
