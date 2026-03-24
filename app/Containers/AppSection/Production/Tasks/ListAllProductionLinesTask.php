<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Data\Repositories\ProductionLineRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class ListAllProductionLinesTask extends ParentTask
{
    public function __construct(
        private readonly ProductionLineRepository $repository,
    ) {}

    public function run(): mixed
    {
        return $this->repository->with('departments')->orderBy('sort_order')->paginate();
    }
}
