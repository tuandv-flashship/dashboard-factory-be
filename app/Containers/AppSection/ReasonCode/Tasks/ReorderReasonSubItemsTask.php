<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Data\Repositories\ReasonSubItemRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class ReorderReasonSubItemsTask extends ParentTask
{
    public function __construct(
        private readonly ReasonSubItemRepository $repository,
    ) {}

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
