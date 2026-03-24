<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Data\Repositories\DepartmentRepository;
use App\Containers\AppSection\Production\Models\Department;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class FindDepartmentByIdTask extends ParentTask
{
    public function __construct(
        private readonly DepartmentRepository $repository,
    ) {}

    public function run(int $id): Department
    {
        return $this->repository->with('productionLine')->find($id);
    }
}
