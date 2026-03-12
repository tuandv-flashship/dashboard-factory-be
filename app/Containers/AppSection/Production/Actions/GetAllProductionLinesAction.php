<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Tasks\GetAllProductionLinesTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Database\Eloquent\Collection;

final class GetAllProductionLinesAction extends ParentAction
{
    public function __construct(
        private readonly GetAllProductionLinesTask $task,
    ) {
    }

    public function run(): Collection
    {
        return $this->task->run();
    }
}
