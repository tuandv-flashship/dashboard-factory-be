<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Tasks\ListAllDepartmentsTask;
use App\Containers\AppSection\Production\UI\API\Requests\ListDepartmentsRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ListAllDepartmentsAction extends ParentAction
{
    public function run(ListDepartmentsRequest $request): mixed
    {
        return app(ListAllDepartmentsTask::class)->run();
    }
}
