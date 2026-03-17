<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\DeleteReasonSubItemTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\DeleteReasonSubItemRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteReasonSubItemAction extends ParentAction
{
    public function run(DeleteReasonSubItemRequest $request): bool
    {
        return app(DeleteReasonSubItemTask::class)->run($request->id);
    }
}
