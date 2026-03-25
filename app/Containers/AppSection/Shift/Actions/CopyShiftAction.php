<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Tasks\CopyShiftToDatesTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class CopyShiftAction extends ParentAction
{
    public function run(int $id, array $targetDates): array
    {
        return DB::transaction(function () use ($id, $targetDates) {
            $source = Shift::findOrFail($id);

            return app(CopyShiftToDatesTask::class)->run($source, $targetDates);
        });
    }
}
