<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\ReorderReasonSubItemsTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ReorderReasonSubItemsRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ReorderReasonSubItemsAction extends ParentAction
{
    public function run(ReorderReasonSubItemsRequest $request): void
    {
        app(ReorderReasonSubItemsTask::class)->run($request->items);
    }
}
