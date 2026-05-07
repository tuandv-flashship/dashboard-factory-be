<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Tasks\UnresolveHourlyIssueTask;
use App\Containers\AppSection\Production\UI\API\Requests\UnresolveHourlyIssueRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use App\Ship\Supports\DepartmentScope;
use Illuminate\Auth\Access\AuthorizationException;

final class UnresolveHourlyIssueAction extends ParentAction
{
    public function __construct(
        private readonly UnresolveHourlyIssueTask $task,
    ) {
    }

    public function run(UnresolveHourlyIssueRequest $request): HourlyIssue
    {
        // Verify department scope
        $issue = HourlyIssue::with('hourlyRecord')->findOrFail($request->id);
        if (!DepartmentScope::check(auth()->user(), 'hourly-issues.resolve', $issue->hourlyRecord->department_id)) {
            throw new AuthorizationException('You do not have access to this department.');
        }

        return $this->task->run($request->id);
    }
}
