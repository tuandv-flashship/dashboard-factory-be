<?php

namespace App\Containers\AppSection\KpiRatingLevel\Tasks;

use App\Containers\AppSection\KpiRatingLevel\Data\Repositories\KpiRatingLevelRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class DeleteKpiRatingLevelTask extends ParentTask
{
    public function __construct(
        private readonly KpiRatingLevelRepository $repository,
    ) {}

    public function run(int $id): bool
    {
        return (bool) $this->repository->delete($id);
    }
}
