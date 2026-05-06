<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Tasks\GetIssueSummaryTask;
use App\Containers\AppSection\Production\UI\API\Requests\GetIssueSummaryRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetIssueSummaryAction extends ParentAction
{
    public function __construct(
        private readonly GetIssueSummaryTask $task,
    ) {
    }

    public function run(GetIssueSummaryRequest $request): array
    {
        return $this->task->run(
            date: $request->input('date'),
            shift: $request->input('shift') ? (int) $request->input('shift') : null,
            departmentId: $request->input('department_id') ? (int) $request->input('department_id') : null,
            category: $request->input('category'),
            dateFrom: $request->input('date_from'),
            dateTo: $request->input('date_to'),
        );
    }
}
