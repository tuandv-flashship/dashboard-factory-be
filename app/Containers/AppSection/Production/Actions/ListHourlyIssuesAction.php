<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Tasks\ListHourlyIssuesTask;
use App\Containers\AppSection\Production\UI\API\Requests\ListHourlyIssuesRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Collection;

final class ListHourlyIssuesAction extends ParentAction
{
    public function __construct(
        private readonly ListHourlyIssuesTask $task,
    ) {
    }

    public function run(ListHourlyIssuesRequest $request): Collection
    {
        return $this->task->run(
            date: $request->input('date'),
            shift: $request->input('shift') ? (int) $request->input('shift') : null,
            departmentId: $request->input('department_id') ? (int) $request->input('department_id') : null,
            category: $request->input('category'),
            resolved: $request->has('resolved') ? $request->boolean('resolved') : null,
            dateFrom: $request->input('date_from'),
            dateTo: $request->input('date_to'),
        );
    }
}
