<?php

namespace App\Containers\AppSection\Machine\Tasks;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class UpdateMachineStatusTask extends ParentTask
{
    public function run(int $machineId, string $status): Machine
    {
        $machine = Machine::query()->findOrFail($machineId);
        $machine->update(['status' => $status]);

        return $machine->refresh();
    }
}
