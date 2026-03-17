<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Data\Repositories\ReasonErrorRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class DeleteReasonErrorTask extends ParentTask
{
    public function __construct(
        private readonly ReasonErrorRepository $repository,
    ) {}

    public function run(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
