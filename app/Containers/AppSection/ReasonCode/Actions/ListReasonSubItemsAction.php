<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\ListReasonSubItemsTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ListReasonSubItemsRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListReasonSubItemsAction extends ParentAction
{
    public function run(ListReasonSubItemsRequest $request): LengthAwarePaginator
    {
        return app(ListReasonSubItemsTask::class)->run();
    }
}
