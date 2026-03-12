<?php

namespace App\Containers\AppSection\Authorization\Tasks;

use App\Containers\AppSection\Authorization\Data\Repositories\RoleRepository;
use App\Containers\AppSection\Authorization\Models\Role;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class UpdateRoleTask extends ParentTask
{
    public function __construct(
        private readonly RoleRepository $repository,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function run(int $id, array $data): Role
    {
        return $this->repository->update($data, $id);
    }
}
