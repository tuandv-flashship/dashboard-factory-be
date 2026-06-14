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
            // Build explicit scope map: permission_id → department_ids JSON
            $scopeMap = collect($permissionScopes ?? [])->keyBy('permission_id');

            // Resolve inherited scope: if a permission has no explicit scope,
            // inherit from its closest scopeable ancestor (group.department-data → children).
            $allPermissions = \App\Ship\Supports\PermissionRegistry::all();
            $flagById = \App\Containers\AppSection\Authorization\Models\Permission::whereIn('id', $permissionIds)
                ->pluck('name', 'id');

            // Build flag → parent_flag map
            $parentMap = collect($allPermissions)->pluck('parent_flag', 'flag');

            // For each permission, walk up the tree to find inherited scope
            $resolvedScope = [];
            foreach ($permissionIds as $permId) {
                if ($scopeMap->has($permId)) {
                    $resolvedScope[$permId] = json_encode($scopeMap[$permId]['department_ids']);
                    continue;
                }

                // Walk up parent chain to find inherited scope
                $flag = $flagById[$permId] ?? null;
                $inherited = null;
                $visited = [];
                while ($flag && !isset($visited[$flag])) {
                    $visited[$flag] = true;
                    $parentFlag = $parentMap[$flag] ?? null;
                    if (!$parentFlag) {
                        break;
                    }
                    // Find parent's permission_id and check if it has scope
                    $parentId = $flagById->search($parentFlag);
                    if ($parentId !== false && $scopeMap->has($parentId)) {
                        $inherited = json_encode($scopeMap[$parentId]['department_ids']);
                        break;
                    }
                    $flag = $parentFlag;
                }

                $resolvedScope[$permId] = $inherited; // null if truly non-scoped
            }

            $syncData = [];
            foreach ($permissionIds as $permId) {
                $syncData[$permId] = [
                    'department_ids' => $resolvedScope[$permId] ?? null,
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
