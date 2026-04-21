<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Tasks\DeleteHourlyIssueTask;
use App\Containers\AppSection\Production\UI\API\Requests\DeleteHourlyIssueRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteHourlyIssueAction extends ParentAction
{
    public function __construct(
        private readonly DeleteHourlyIssueTask $task,
    ) {
    }

    public function run(DeleteHourlyIssueRequest $request): void
    {
        $this->task->run($request->id);
    }
}
