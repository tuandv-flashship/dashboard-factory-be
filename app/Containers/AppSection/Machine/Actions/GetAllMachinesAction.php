<?php

namespace App\Containers\AppSection\Machine\Actions;

use App\Containers\AppSection\Machine\Tasks\GetAllMachinesTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Database\Eloquent\Collection;

final class GetAllMachinesAction extends ParentAction
{
    public function __construct(
        private readonly GetAllMachinesTask $task,
    ) {
    }

    public function run(): Collection
    {
        return $this->task->run();
    }
}
