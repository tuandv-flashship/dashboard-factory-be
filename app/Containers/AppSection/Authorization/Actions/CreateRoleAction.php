<?php

namespace App\Containers\AppSection\Authorization\Actions;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\Authorization\Models\Role;
use App\Containers\AppSection\Authorization\Tasks\CreateRoleTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class CreateRoleAction extends ParentAction
{
    public function __construct(
        private readonly CreateRoleTask $createRoleTask,
    ) {
    }

    /**
     * @param int[]|null $permissionIds
     */
    public function run(
        string $name,
        string|null $description = null,
        string|null $displayName = null,
        ?array $permissionIds = null
    ): Role
    {
        $role = $this->createRoleTask->run($name, $description, $displayName);

        if ($permissionIds !== null) {
            $role->syncPermissions($permissionIds);
            $role = $role->refresh();
        }

        AuditLogRecorder::recordModel('created', $role);

        return $role;
    }
}
