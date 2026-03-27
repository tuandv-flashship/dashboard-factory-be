<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Tasks\ListAllProductionLinesTask;
use App\Containers\AppSection\Production\UI\API\Requests\ListProductionLinesRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ListAllProductionLinesAction extends ParentAction
{
    public function run(ListProductionLinesRequest $request): mixed
    {
        $deptFactory = $request->query('dept_factory');
        $deptActive  = $request->has('dept_active') ? (bool) $request->query('dept_active') : null;

        return app(ListAllProductionLinesTask::class)->run($deptFactory, $deptActive);
    }
}
