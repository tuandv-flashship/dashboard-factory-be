<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Tasks\GetLineSummaryTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetLineSummaryAction extends ParentAction
{
    public function __construct(
        private readonly GetLineSummaryTask $task,
    ) {
    }

    public function run(string $lineCode, ?string $date = null, ?int $shiftNumber = null): array
    {
        return $this->task->run($lineCode, $date, $shiftNumber);
    }
}
