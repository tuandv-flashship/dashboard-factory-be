<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Tasks\FindShiftTemplateByIdTask;
use App\Containers\AppSection\Shift\Tasks\DeleteShiftTemplateTask;
use App\Containers\AppSection\Shift\UI\API\Requests\DeleteShiftTemplateRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteShiftTemplateAction extends ParentAction
{
    public function run(DeleteShiftTemplateRequest $request): void
    {
        $template = app(FindShiftTemplateByIdTask::class)->run($request->id);
        app(DeleteShiftTemplateTask::class)->run($template);
    }
}
