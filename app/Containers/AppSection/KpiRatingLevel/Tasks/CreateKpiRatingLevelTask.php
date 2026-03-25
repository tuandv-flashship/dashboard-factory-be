<?php

namespace App\Containers\AppSection\KpiRatingLevel\Tasks;

use App\Containers\AppSection\KpiRatingLevel\Data\Repositories\KpiRatingLevelRepository;
use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class CreateKpiRatingLevelTask extends ParentTask
{
    public function __construct(
        private readonly KpiRatingLevelRepository $repository,
    ) {}

    public function run(array $data): KpiRatingLevel
    {
        return $this->repository->create($data);
    }
}
