<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\ReorderReasonSubItemsTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ReorderReasonSubItemsRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ReorderReasonSubItemsAction extends ParentAction
{
    public function __construct(
        private readonly ReorderReasonSubItemsTask $task,
    ) {}

    public function run(ReorderReasonSubItemsRequest $request): void
    {
        $this->task->run($request->items);
    }
}
