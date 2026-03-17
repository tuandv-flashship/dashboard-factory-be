<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Data\Repositories\ReasonErrorRepository;
use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class UpdateReasonErrorTask extends ParentTask
{
    public function __construct(
        private readonly ReasonErrorRepository $repository,
    ) {}

    public function run(int $id, array $data): ReasonError
    {
        return $this->repository->update($data, $id);
    }
}
