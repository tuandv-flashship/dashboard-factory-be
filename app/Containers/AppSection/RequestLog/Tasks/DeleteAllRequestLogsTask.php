<?php

namespace App\Containers\AppSection\RequestLog\Tasks;

use App\Containers\AppSection\RequestLog\Data\Repositories\RequestLogRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class DeleteAllRequestLogsTask extends ParentTask
{
    public function __construct(
        private readonly RequestLogRepository $repository,
    ) {
    }

    public function run(): void
    {
        $this->repository
            ->getModel()
            ->newQuery()
            ->truncate();
    }
}
