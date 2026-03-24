<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Production\Tasks\FindProductionLineByIdTask;
use App\Containers\AppSection\Production\UI\API\Requests\FindProductionLineRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class FindProductionLineAction extends ParentAction
{
    public function run(FindProductionLineRequest $request): ProductionLine
    {
        return app(FindProductionLineByIdTask::class)->run($request->id);
    }
}
