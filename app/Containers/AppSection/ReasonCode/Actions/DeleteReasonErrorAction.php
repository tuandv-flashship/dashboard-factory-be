<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\DeleteReasonErrorTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\DeleteReasonErrorRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteReasonErrorAction extends ParentAction
{
    public function run(DeleteReasonErrorRequest $request): bool
    {
        return app(DeleteReasonErrorTask::class)->run($request->id);
    }
}
