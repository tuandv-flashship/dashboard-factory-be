<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Data\Repositories\ReasonCategoryRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class ReorderReasonCategoriesTask extends ParentTask
{
    public function __construct(
        private readonly ReasonCategoryRepository $repository,
    ) {}

    /**
     * @param array<int, int> $items [{id: x, sort_order: y}, ...]
     */
    public function run(array $items): void
    {
        foreach ($items as $item) {
            $this->repository->update(
                ['sort_order' => $item['sort_order']],
                $item['id'],
            );
        }
    }
}
