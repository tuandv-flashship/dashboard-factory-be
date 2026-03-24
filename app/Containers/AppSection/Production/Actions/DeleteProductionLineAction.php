<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Tasks\DeleteProductionLineTask;
use App\Containers\AppSection\Production\Tasks\FindProductionLineByIdTask;
use App\Containers\AppSection\Production\UI\API\Requests\DeleteProductionLineRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteProductionLineAction extends ParentAction
{
    public function run(DeleteProductionLineRequest $request): bool
    {
        app(FindProductionLineByIdTask::class)->run($request->id);

        return app(DeleteProductionLineTask::class)->run($request->id);
    }
}
