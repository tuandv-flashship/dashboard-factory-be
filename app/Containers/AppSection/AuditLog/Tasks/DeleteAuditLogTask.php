<?php

namespace App\Containers\AppSection\AuditLog\Tasks;

use App\Containers\AppSection\AuditLog\Data\Repositories\AuditHistoryRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class DeleteAuditLogTask extends ParentTask
{
    public function __construct(
        private readonly AuditHistoryRepository $repository,
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
