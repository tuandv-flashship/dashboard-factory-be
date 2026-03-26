<?php

namespace App\Containers\AppSection\Department\Tasks;

use App\Containers\AppSection\Department\Data\Repositories\DepartmentRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class DeleteDepartmentTask extends ParentTask
{
    public function __construct(
        private readonly DepartmentRepository $repository,
    ) {}

    public function run(int $id): bool
    {
        return (bool) $this->repository->delete($id);
    }
}
