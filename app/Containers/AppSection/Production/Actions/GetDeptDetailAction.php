<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Tasks\GetDeptDetailTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetDeptDetailAction extends ParentAction
{
    public function __construct(
        private readonly GetDeptDetailTask $task,
    ) {
    }

    public function run(string $lineCode, string $deptCode, ?string $date = null, ?int $shiftNumber = null): array
    {
        return $this->task->run($lineCode, $deptCode, $date, $shiftNumber);
    }
}
