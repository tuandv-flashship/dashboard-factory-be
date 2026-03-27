<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Data\Repositories\ProductionLineRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class DeleteProductionLineTask extends ParentTask
{
    public function __construct(
        private readonly ProductionLineRepository $repository,
    ) {}

    public function run(mixed $id): bool
    {
        return (bool) $this->repository->delete($id);
    }
}
