<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Tasks\DeleteHourlyIssueTask;
use App\Containers\AppSection\Production\UI\API\Requests\DeleteHourlyIssueRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use App\Ship\Supports\DepartmentScope;
use Illuminate\Auth\Access\AuthorizationException;

final class DeleteHourlyIssueAction extends ParentAction
{
    public function __construct(
        private readonly DeleteHourlyIssueTask $task,
    ) {
    }

    public function run(DeleteHourlyIssueRequest $request): void
    {
        // Verify department scope
        $issue = HourlyIssue::with('hourlyRecord')->findOrFail($request->id);
        if (!DepartmentScope::check(auth()->user(), 'hourly-issues.destroy', $issue->hourlyRecord->department_id)) {
            throw new AuthorizationException('You do not have access to this department.');
        }

        $this->task->run($request->id);
    }
}
