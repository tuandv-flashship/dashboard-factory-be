<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Tasks\ResolveHourlyIssueTask;
use App\Containers\AppSection\Production\UI\API\Requests\ResolveHourlyIssueRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use App\Ship\Supports\DepartmentScope;
use Illuminate\Auth\Access\AuthorizationException;

final class ResolveHourlyIssueAction extends ParentAction
{
    public function __construct(
        private readonly ResolveHourlyIssueTask $task,
    ) {
    }

    public function run(ResolveHourlyIssueRequest $request): HourlyIssue
    {
        // Verify department scope
        $issue = HourlyIssue::with('hourlyRecord')->findOrFail($request->id);
        if (!DepartmentScope::check(auth()->user(), 'hourly-issues.edit', $issue->hourlyRecord->department_id)) {
            throw new AuthorizationException('You do not have access to this department.');
        }

        return $this->task->run($request->id, $request->input('resolution'));
    }
}
