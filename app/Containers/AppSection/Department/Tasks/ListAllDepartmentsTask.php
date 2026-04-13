<?php

namespace App\Containers\AppSection\Department\Tasks;

use App\Containers\AppSection\Department\Data\Repositories\DepartmentRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class ListAllDepartmentsTask extends ParentTask
{
    public function __construct(
        private readonly DepartmentRepository $repository,
    ) {}

    public function run(): mixed
    {
        return $this->repository->with(['productionLine', 'machines'])->orderBy('sort_order')->paginate();
    }
}
