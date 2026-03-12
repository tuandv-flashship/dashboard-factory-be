<?php

namespace App\Containers\AppSection\AuditLog\Tasks;

use App\Containers\AppSection\AuditLog\Data\Repositories\AuditHistoryRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListAuditLogsTask extends ParentTask
{
    public function __construct(
        private readonly AuditHistoryRepository $repository,
    ) {
    }

    public function run(): LengthAwarePaginator
    {
        return $this->repository
            ->scope(fn ($query) => $query->with(['user', 'actor'])->latest())
            ->addRequestCriteria()
            ->paginate();
    }
}
