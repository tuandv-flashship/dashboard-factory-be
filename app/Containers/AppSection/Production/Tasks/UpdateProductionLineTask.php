<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Data\Repositories\ProductionLineRepository;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class UpdateProductionLineTask extends ParentTask
{
    public function __construct(
        private readonly ProductionLineRepository $repository,
    ) {}

    public function run(mixed $id, array $data): ProductionLine
    {
        return $this->repository->update($data, $id);
    }
}
