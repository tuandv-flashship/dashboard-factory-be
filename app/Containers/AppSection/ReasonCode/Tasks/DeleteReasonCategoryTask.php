<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Data\Repositories\ReasonCategoryRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class DeleteReasonCategoryTask extends ParentTask
{
    public function __construct(
        private readonly ReasonCategoryRepository $repository,
    ) {}

    public function run(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
