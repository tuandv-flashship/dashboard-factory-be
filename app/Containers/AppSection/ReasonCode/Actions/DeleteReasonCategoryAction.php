<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\DeleteReasonCategoryTask;
use App\Containers\AppSection\ReasonCode\Tasks\FindReasonCategoryByIdTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\DeleteReasonCategoryRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteReasonCategoryAction extends ParentAction
{
    public function run(DeleteReasonCategoryRequest $request): bool
    {
        app(FindReasonCategoryByIdTask::class)->run($request->id);

        return app(DeleteReasonCategoryTask::class)->run($request->id);
    }
}
