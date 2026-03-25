<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Tasks\FindShiftTemplateByIdTask;
use App\Containers\AppSection\Shift\UI\API\Requests\FindShiftTemplateRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class FindShiftTemplateAction extends ParentAction
{
    public function run(FindShiftTemplateRequest $request): ShiftTemplate
    {
        return app(FindShiftTemplateByIdTask::class)->run($request->id);
    }
}
