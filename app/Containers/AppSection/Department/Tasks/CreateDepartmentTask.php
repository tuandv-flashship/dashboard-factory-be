<?php

namespace App\Containers\AppSection\Department\Tasks;

use App\Containers\AppSection\Department\Data\Repositories\DepartmentRepository;
use App\Containers\AppSection\Department\Models\Department;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class CreateDepartmentTask extends ParentTask
{
    public function __construct(
        private readonly DepartmentRepository $repository,
    ) {}

    public function run(array $data): Department
    {
        return $this->repository->create($data);
    }
}
