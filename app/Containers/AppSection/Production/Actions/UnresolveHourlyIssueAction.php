<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Tasks\UnresolveHourlyIssueTask;
use App\Containers\AppSection\Production\UI\API\Requests\UnresolveHourlyIssueRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class UnresolveHourlyIssueAction extends ParentAction
{
    public function __construct(
        private readonly UnresolveHourlyIssueTask $task,
    ) {
    }

    public function run(UnresolveHourlyIssueRequest $request): HourlyIssue
    {
        return $this->task->run($request->id);
    }
}
