<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\ListReasonCategoriesTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ListReasonCategoriesRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListReasonCategoriesAction extends ParentAction
{
    public function __construct(
        private readonly ListReasonCategoriesTask $task,
    ) {}

    public function run(ListReasonCategoriesRequest $request): LengthAwarePaginator
    {
        return $this->task->run();
    }
}
