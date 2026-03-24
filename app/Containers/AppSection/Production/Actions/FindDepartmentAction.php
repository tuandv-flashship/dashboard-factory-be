<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\Department;
use App\Containers\AppSection\Production\Tasks\FindDepartmentByIdTask;
use App\Containers\AppSection\Production\UI\API\Requests\FindDepartmentRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class FindDepartmentAction extends ParentAction
{
    public function run(FindDepartmentRequest $request): Department
    {
        return app(FindDepartmentByIdTask::class)->run($request->id);
    }
}
