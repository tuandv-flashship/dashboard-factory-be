<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Data\Repositories\ReasonSubItemRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class DeleteReasonSubItemTask extends ParentTask
{
    public function __construct(
        private readonly ReasonSubItemRepository $repository,
    ) {}

    public function run(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
