<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\ListReasonErrorsTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\ListReasonErrorsRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListReasonErrorsAction extends ParentAction
{
    public function run(ListReasonErrorsRequest $request): LengthAwarePaginator
    {
        return app(ListReasonErrorsTask::class)->run();
    }
}
