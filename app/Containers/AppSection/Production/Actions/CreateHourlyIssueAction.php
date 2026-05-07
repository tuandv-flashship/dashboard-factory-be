<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Tasks\CreateHourlyIssueTask;
use App\Containers\AppSection\Production\UI\API\Requests\CreateHourlyIssueRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use App\Ship\Supports\DepartmentScope;
use Illuminate\Auth\Access\AuthorizationException;

final class CreateHourlyIssueAction extends ParentAction
{
    public function __construct(
        private readonly CreateHourlyIssueTask $task,
    ) {
    }

    public function run(CreateHourlyIssueRequest $request): HourlyIssue
    {
        // Verify department scope
        $record = HourlyRecord::findOrFail($request->id);
        if (!DepartmentScope::check(auth()->user(), 'hourly-issues.create', $record->department_id)) {
            throw new AuthorizationException('You do not have access to this department.');
        }

        return $this->task->run($request->id, $request->validated());
    }
}
