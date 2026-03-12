<?php

namespace App\Containers\AppSection\AuditLog\Tasks;

use App\Containers\AppSection\AuditLog\Data\Repositories\AuditHistoryRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class DeleteAllAuditLogsTask extends ParentTask
{
    public function __construct(
        private readonly AuditHistoryRepository $repository,
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
