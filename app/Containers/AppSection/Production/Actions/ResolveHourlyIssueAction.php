<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Tasks\ResolveHourlyIssueTask;
use App\Containers\AppSection\Production\UI\API\Requests\ResolveHourlyIssueRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ResolveHourlyIssueAction extends ParentAction
{
    public function __construct(
        private readonly ResolveHourlyIssueTask $task,
    ) {
    }

    public function run(ResolveHourlyIssueRequest $request): HourlyIssue
    {
        return $this->task->run($request->id, $request->input('resolution'));
    }
}
