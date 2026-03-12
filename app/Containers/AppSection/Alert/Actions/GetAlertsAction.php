<?php

namespace App\Containers\AppSection\Alert\Actions;

use App\Containers\AppSection\Alert\Tasks\GetAlertsTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Database\Eloquent\Collection;

final class GetAlertsAction extends ParentAction
{
    public function __construct(
        private readonly GetAlertsTask $task,
    ) {
    }

    public function run(?string $line = null): Collection
    {
        return $this->task->run($line);
    }
}
