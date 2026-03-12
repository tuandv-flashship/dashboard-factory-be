<?php

namespace App\Containers\AppSection\RequestLog\Tasks;

use App\Containers\AppSection\RequestLog\Data\Repositories\RequestLogRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class DeleteRequestLogTask extends ParentTask
{
    public function __construct(
        private readonly RequestLogRepository $repository,
    ) {
    }

    public function run(int $id): void
    {
        $this->repository
            ->getModel()
            ->newQuery()
            ->whereKey($id)
            ->delete();
    }
}
