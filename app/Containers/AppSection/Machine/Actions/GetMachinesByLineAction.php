<?php

namespace App\Containers\AppSection\Machine\Actions;

use App\Containers\AppSection\Machine\Tasks\GetMachinesByLineTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Database\Eloquent\Collection;

final class GetMachinesByLineAction extends ParentAction
{
    public function __construct(
        private readonly GetMachinesByLineTask $task,
    ) {
    }

    public function run(string $line): Collection
    {
        return $this->task->run($line);
    }
}
