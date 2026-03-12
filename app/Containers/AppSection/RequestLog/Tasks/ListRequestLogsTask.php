<?php

namespace App\Containers\AppSection\RequestLog\Tasks;

use App\Containers\AppSection\RequestLog\Data\Repositories\RequestLogRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListRequestLogsTask extends ParentTask
{
    public function __construct(
        private readonly RequestLogRepository $repository,
    ) {
    }

    public function run(): LengthAwarePaginator
    {
        return $this->repository
            ->scope(fn ($query) => $query->latest())
            ->addRequestCriteria()
            ->paginate();
    }
}
