<?php

namespace App\Containers\AppSection\KpiRatingLevel\Tasks;

use App\Containers\AppSection\KpiRatingLevel\Data\Repositories\KpiRatingLevelRepository;
use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class FindKpiRatingLevelByIdTask extends ParentTask
{
    public function __construct(
        private readonly KpiRatingLevelRepository $repository,
    ) {}

    public function run(int $id): KpiRatingLevel
    {
        return $this->repository->with('details')->find($id);
    }
}
