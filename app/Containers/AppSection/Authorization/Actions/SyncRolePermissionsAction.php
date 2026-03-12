<?php

namespace App\Containers\AppSection\Authorization\Actions;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\Authorization\Models\Role;
use App\Containers\AppSection\Authorization\Tasks\FindRoleTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class SyncRolePermissionsAction extends ParentAction
{
    public function __construct(
        private readonly FindRoleTask $findRoleTask,
    ) {
    }

    public function run(int $roleId, int ...$permissionIds): Role
    {
        $role = $this->findRoleTask->run($roleId)
            ->syncPermissions($permissionIds);

        AuditLogRecorder::recordModel('updated', $role);

        return $role;
    }
}
