<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Tasks\UpdateHourlyIssueTask;
use App\Containers\AppSection\Production\UI\API\Requests\UpdateHourlyIssueRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class UpdateHourlyIssueAction extends ParentAction
{
    public function __construct(
        private readonly UpdateHourlyIssueTask $task,
    ) {
    }

    public function run(UpdateHourlyIssueRequest $request): HourlyIssue
    {
        return $this->task->run($request->id, $request->validated());
    }
}
