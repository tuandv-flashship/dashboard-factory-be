<?php

namespace App\Containers\AppSection\Order\Actions;

use App\Containers\AppSection\Order\Tasks\GetOrderSummaryTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetOrderSummaryAction extends ParentAction
{
    public function __construct(
        private readonly GetOrderSummaryTask $task,
    ) {
    }

    public function run(?string $date = null, ?int $shiftNumber = null): array
    {
        return $this->task->run($date, $shiftNumber);
    }
}
