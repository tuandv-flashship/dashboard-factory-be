<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Data\Repositories\ReasonSubItemRepository;
use App\Containers\AppSection\ReasonCode\Models\ReasonSubItem;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class UpdateReasonSubItemTask extends ParentTask
{
    public function __construct(
        private readonly ReasonSubItemRepository $repository,
    ) {}

    public function run(int $id, array $data): ReasonSubItem
    {
        return $this->repository->update($data, $id);
    }
}
