<?php

namespace App\Containers\AppSection\Department\Actions;

use App\Containers\AppSection\Department\Tasks\ListAllDepartmentsTask;
use App\Containers\AppSection\Department\UI\API\Requests\ListDepartmentsRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ListAllDepartmentsAction extends ParentAction
{
    public function run(ListDepartmentsRequest $request): mixed
    {
        return app(ListAllDepartmentsTask::class)->run();
    }
}
