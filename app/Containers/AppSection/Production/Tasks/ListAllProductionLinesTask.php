<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Data\Criteria\DepartmentFilterCriteria;
use App\Containers\AppSection\Production\Data\Repositories\ProductionLineRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class ListAllProductionLinesTask extends ParentTask
{
    public function __construct(
        private readonly ProductionLineRepository $repository,
    ) {}

    public function run(?string $deptFactory = null, ?bool $deptActive = null): mixed
    {
        return $this->repository
            ->pushCriteria(new DepartmentFilterCriteria($deptFactory, $deptActive))
            ->addRequestCriteria()
            ->orderBy('sort_order')
            ->paginate();
    }
}


