<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\ListReasonCategoriesTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ListReasonCategoriesRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListReasonCategoriesAction extends ParentAction
{
    public function run(ListReasonCategoriesRequest $request): LengthAwarePaginator
    {
        return app(ListReasonCategoriesTask::class)->run();
    }
}
