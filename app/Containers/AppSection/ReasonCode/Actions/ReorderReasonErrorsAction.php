<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\ReorderReasonErrorsTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ReorderReasonErrorsRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ReorderReasonErrorsAction extends ParentAction
{
    public function run(ReorderReasonErrorsRequest $request): void
    {
        app(ReorderReasonErrorsTask::class)->run($request->items);
    }
}
