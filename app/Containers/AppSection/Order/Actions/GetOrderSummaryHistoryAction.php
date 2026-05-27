<?php

namespace App\Containers\AppSection\Order\Actions;

use App\Containers\AppSection\Order\Tasks\GetOrderSummaryHistoryTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Collection;

final class GetOrderSummaryHistoryAction extends ParentAction
{
    public function __construct(
        private readonly GetOrderSummaryHistoryTask $task,
    ) {
    }

    public function run(int $days = 10, ?string $line = null): Collection
    {
        return $this->task->run($days, $line);
    }
}
