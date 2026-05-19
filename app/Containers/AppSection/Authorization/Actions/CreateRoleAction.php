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
     * @param array<int, array{permission_id: int, department_ids: int[]}>|null $permissionScopes
     */
    public function run(
        string $name,
        string|null $description = null,
        string|null $displayName = null,
        ?array $permissionIds = null,
        ?array $permissionScopes = null
    ): Role
    {
        $role = $this->createRoleTask->run($name, $description, $displayName);

        if ($permissionIds !== null) {
            $scopeMap = collect($permissionScopes ?? [])->keyBy('permission_id');

            $syncData = [];
            foreach ($permissionIds as $permId) {
                $syncData[$permId] = [
                    'department_ids' => $scopeMap->has($permId)
                        ? json_encode($scopeMap[$permId]['department_ids'])
                        : null,
                ];
            }

            $role->permissions()->sync($syncData);
            $role->load('permissions');
        }

        AuditLogRecorder::recordModel('created', $role);

        return $role;
    }
}
