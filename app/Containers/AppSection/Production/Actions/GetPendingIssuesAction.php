<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Tasks\GetPendingIssuesTask;
use App\Containers\AppSection\Production\UI\API\Requests\GetPendingIssuesRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Collection;

final class GetPendingIssuesAction extends ParentAction
{
    public function __construct(
        private readonly GetPendingIssuesTask $task,
    ) {
    }

    public function run(GetPendingIssuesRequest $request): Collection
    {
        $date         = $request->input('date');
        $shift        = $request->input('shift') ? (int) $request->input('shift') : null;
        $departmentId = $request->input('department_id') ? (int) $request->input('department_id') : null;

        return $this->task->run($date, $shift, $departmentId);
    }
}
