<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Tasks\DeleteDepartmentTask;
use App\Containers\AppSection\Production\Tasks\FindDepartmentByIdTask;
use App\Containers\AppSection\Production\UI\API\Requests\DeleteDepartmentRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteDepartmentAction extends ParentAction
{
    public function run(DeleteDepartmentRequest $request): bool
    {
        app(FindDepartmentByIdTask::class)->run($request->id);

        return app(DeleteDepartmentTask::class)->run($request->id);
    }
}
