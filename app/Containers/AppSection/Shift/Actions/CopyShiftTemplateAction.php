<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Tasks\CopyShiftTemplateTask;
use App\Containers\AppSection\Shift\Tasks\FindShiftTemplateByIdTask;
use App\Containers\AppSection\Shift\UI\API\Requests\CopyShiftTemplateRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class CopyShiftTemplateAction extends ParentAction
{
    public function run(CopyShiftTemplateRequest $request): ShiftTemplate
    {
        return DB::transaction(function () use ($request) {
            $source = app(FindShiftTemplateByIdTask::class)->run($request->id);

            return app(CopyShiftTemplateTask::class)->run($source);
        });
    }
}
