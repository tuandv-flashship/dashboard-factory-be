<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Tasks\ListAllProductionLinesTask;
use App\Containers\AppSection\Production\UI\API\Requests\ListProductionLinesRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ListAllProductionLinesAction extends ParentAction
{
    public function run(ListProductionLinesRequest $request): mixed
    {
        return app(ListAllProductionLinesTask::class)->run();
    }
}
