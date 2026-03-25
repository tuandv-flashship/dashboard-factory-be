<?php

namespace App\Containers\AppSection\KpiRatingLevel\Tasks;

use App\Containers\AppSection\KpiRatingLevel\Data\Repositories\KpiRatingLevelRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListKpiRatingLevelsTask extends ParentTask
{
    public function __construct(
        private readonly KpiRatingLevelRepository $repository,
    ) {}

    public function run(): LengthAwarePaginator
    {
        return $this->repository
            ->addRequestCriteria()
            ->with('details')
            ->orderBy('effective_from', 'desc')
            ->paginate();
    }
}
