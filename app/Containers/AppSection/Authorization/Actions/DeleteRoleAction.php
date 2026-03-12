<?php

namespace App\Containers\AppSection\Authorization\Actions;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\Authorization\Data\Repositories\RoleRepository;
use App\Containers\AppSection\Authorization\Tasks\FindRoleTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteRoleAction extends ParentAction
{
    public function __construct(
        private readonly RoleRepository $repository,
        private readonly FindRoleTask $findRoleTask,
    ) {
    }

    public function run(int $id): bool
    {
        $role = $this->findRoleTask->run($id);
        $deleted = $this->repository->delete($id);

        if ($deleted) {
            AuditLogRecorder::recordModel('deleted', $role);
        }

        return $deleted;
    }
}
