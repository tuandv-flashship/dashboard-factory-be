<?php

namespace App\Containers\AppSection\Authorization\Actions;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\Authorization\Data\Repositories\RoleRepository;
use App\Containers\AppSection\Authorization\Models\Role;
use App\Containers\AppSection\Authorization\Tasks\FindRoleTask;
use App\Containers\AppSection\Authorization\Tasks\UpdateRoleTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Prettus\Repository\Events\RepositoryEntityUpdated;

final class UpdateRoleWithPermissionsAction extends ParentAction
{
    public function __construct(
        private readonly FindRoleTask $findRoleTask,
        private readonly UpdateRoleTask $updateRoleTask,
        private readonly RoleRepository $roleRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param int[]|null $permissionIds
     * @param array<int, array{permission_id: int, department_ids: int[]}>|null $permissionScopes
     */
    public function run(int $roleId, array $data, ?array $permissionIds, ?array $permissionScopes = null): Role
    {
        $shouldLog = $data !== [] || $permissionIds !== null;

        $role = $data === []
            ? $this->findRoleTask->run($roleId)
            : $this->updateRoleTask->run($roleId, $data);

        if ($permissionIds !== null) {
            // Build sync data with department_ids pivot
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

            // Clear the repository cache so subsequent queries return fresh data
            event(new RepositoryEntityUpdated($this->roleRepository, $role));
        }

        if ($shouldLog) {
            AuditLogRecorder::recordModel('updated', $role);
        }

        return $role;
    }
}
