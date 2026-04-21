<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\DeleteReasonCategoryTask;
use App\Containers\AppSection\ReasonCode\Tasks\FindReasonCategoryByIdTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\DeleteReasonCategoryRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteReasonCategoryAction extends ParentAction
{
    public function __construct(
        private readonly FindReasonCategoryByIdTask $findTask,
        private readonly DeleteReasonCategoryTask $deleteTask,
    ) {}

    public function run(DeleteReasonCategoryRequest $request): bool
    {
        $this->findTask->run($request->id); // 404 if not found

        return $this->deleteTask->run($request->id);
    }
}
