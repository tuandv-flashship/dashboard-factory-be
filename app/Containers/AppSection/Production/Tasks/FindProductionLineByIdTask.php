<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Data\Repositories\ProductionLineRepository;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class FindProductionLineByIdTask extends ParentTask
{
    public function __construct(
        private readonly ProductionLineRepository $repository,
    ) {}

    public function run(mixed $id): ProductionLine
    {
        return $this->repository->with('departments')->find($id);
    }
}
