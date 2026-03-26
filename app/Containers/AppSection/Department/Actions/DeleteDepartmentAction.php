<?php

namespace App\Containers\AppSection\Department\Actions;

use App\Containers\AppSection\Department\Tasks\DeleteDepartmentTask;
use App\Containers\AppSection\Department\Tasks\FindDepartmentByIdTask;
use App\Containers\AppSection\Department\UI\API\Requests\DeleteDepartmentRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteDepartmentAction extends ParentAction
{
    public function run(DeleteDepartmentRequest $request): bool
    {
        app(FindDepartmentByIdTask::class)->run($request->id);

        return app(DeleteDepartmentTask::class)->run($request->id);
    }
}
