<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Tasks\CreateHourlyIssueTask;
use App\Containers\AppSection\Production\UI\API\Requests\CreateHourlyIssueRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class CreateHourlyIssueAction extends ParentAction
{
    public function __construct(
        private readonly CreateHourlyIssueTask $task,
    ) {
    }

    public function run(CreateHourlyIssueRequest $request): HourlyIssue
    {
        return $this->task->run($request->id, $request->validated());
    }
}
