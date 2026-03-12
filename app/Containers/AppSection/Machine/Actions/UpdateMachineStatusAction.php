<?php

namespace App\Containers\AppSection\Machine\Actions;

use App\Containers\AppSection\Machine\Tasks\UpdateMachineStatusTask;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Ship\Parents\Actions\Action as ParentAction;

final class UpdateMachineStatusAction extends ParentAction
{
    public function __construct(
        private readonly UpdateMachineStatusTask $task,
    ) {
    }

    public function run(int $machineId, string $status): Machine
    {
        return $this->task->run($machineId, $status);
    }
}
