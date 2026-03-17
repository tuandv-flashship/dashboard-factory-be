<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\ReorderReasonCategoriesTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ReorderReasonCategoriesRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ReorderReasonCategoriesAction extends ParentAction
{
    public function run(ReorderReasonCategoriesRequest $request): void
    {
        app(ReorderReasonCategoriesTask::class)->run($request->items);
    }
}
