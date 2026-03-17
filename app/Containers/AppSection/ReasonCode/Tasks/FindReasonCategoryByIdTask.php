<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Data\Repositories\ReasonCategoryRepository;
use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class FindReasonCategoryByIdTask extends ParentTask
{
    public function __construct(
        private readonly ReasonCategoryRepository $repository,
    ) {}

    public function run(int $id): ReasonCategory
    {
        return $this->repository
            ->addRequestCriteria()
            ->find($id);
    }
}
