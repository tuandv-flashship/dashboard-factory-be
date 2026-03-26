<?php

namespace App\Containers\AppSection\Department\Actions;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Department\Tasks\FindDepartmentByIdTask;
use App\Containers\AppSection\Department\UI\API\Requests\FindDepartmentRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class FindDepartmentAction extends ParentAction
{
    public function run(FindDepartmentRequest $request): Department
    {
        return app(FindDepartmentByIdTask::class)->run($request->id);
    }
}
