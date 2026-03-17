<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Data\Repositories\ReasonCategoryRepository;
use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class UpdateReasonCategoryTask extends ParentTask
{
    public function __construct(
        private readonly ReasonCategoryRepository $repository,
    ) {}

    public function run(int $id, array $data): ReasonCategory
    {
        return $this->repository->update($data, $id);
    }
}
