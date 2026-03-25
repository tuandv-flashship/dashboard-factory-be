<?php

namespace App\Containers\AppSection\KpiRatingLevel\Tasks;

use App\Containers\AppSection\KpiRatingLevel\Data\Repositories\KpiRatingLevelRepository;
use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class UpdateKpiRatingLevelTask extends ParentTask
{
    public function __construct(
        private readonly KpiRatingLevelRepository $repository,
    ) {}

    public function run(int $id, array $data): KpiRatingLevel
    {
        return $this->repository->update($data, $id);
    }
}
