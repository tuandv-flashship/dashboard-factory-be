<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\ReorderReasonCategoriesTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ReorderReasonCategoriesRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ReorderReasonCategoriesAction extends ParentAction
{
    public function __construct(
        private readonly ReorderReasonCategoriesTask $task,
    ) {}

    public function run(ReorderReasonCategoriesRequest $request): void
    {
        $this->task->run($request->items);
    }
}
