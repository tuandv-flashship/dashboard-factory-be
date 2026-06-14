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

            // ── Resolve inherited scope ──
            // FE may send scopes on children (create/edit) but not on parents (index)
            // or the group. We resolve by finding the "scope group" for each permission
            // (nearest is_department_scopeable ancestor) and sharing scopes within groups.
            $allPermissions = \App\Ship\Supports\PermissionRegistry::all();
            $flagById = \App\Containers\AppSection\Authorization\Models\Permission::whereIn('id', $permissionIds)
                ->pluck('name', 'id');

            // Build lookup maps from registry
            $parentMap = collect($allPermissions)->pluck('parent_flag', 'flag');
            $scopeableFlags = collect($allPermissions)
                ->where('is_department_scopeable', true)
                ->pluck('flag')
                ->flip();

            // For each permission, find its scope group root (nearest is_department_scopeable ancestor or self)
            $permGroupMap = []; // permId → scopeGroupFlag
            foreach ($permissionIds as $permId) {
                $flag = $flagById[$permId] ?? null;
                if (!$flag) {
                    continue;
                }

                // Walk up to find the nearest is_department_scopeable flag (including self)
                $current = $flag;
                $visited = [];
                $groupRoot = null;
                while ($current && !isset($visited[$current])) {
                    $visited[$current] = true;
                    if ($scopeableFlags->has($current)) {
                        $groupRoot = $current;
                        // Don't break — keep walking up to find the highest scopeable ancestor
                    }
                    $parentFlag = $parentMap[$current] ?? null;
                    if (!$parentFlag) {
                        break;
                    }
                    $current = $parentFlag;
                }

                if ($groupRoot !== null) {
                    $permGroupMap[$permId] = $groupRoot;
                }
            }

            // Collect all department_ids per scope group from explicit scopes
            $groupScopes = []; // scopeGroupFlag → int[]
            foreach ($permissionIds as $permId) {
                if (!$scopeMap->has($permId)) {
                    continue;
                }
                $group = $permGroupMap[$permId] ?? null;
                if ($group === null) {
                    continue;
                }
                $deptIds = $scopeMap[$permId]['department_ids'];
                if (!isset($groupScopes[$group])) {
                    $groupScopes[$group] = [];
                }
                $groupScopes[$group] = array_values(array_unique(
                    array_merge($groupScopes[$group], $deptIds)
                ));
            }

            // Build sync data: explicit scope → use it; in a scope group → inherit group scope; else null
            $syncData = [];
            foreach ($permissionIds as $permId) {
                if ($scopeMap->has($permId)) {
                    $syncData[$permId] = [
                        'department_ids' => json_encode($scopeMap[$permId]['department_ids']),
                    ];
                } elseif (isset($permGroupMap[$permId], $groupScopes[$permGroupMap[$permId]])) {
                    $syncData[$permId] = [
                        'department_ids' => json_encode($groupScopes[$permGroupMap[$permId]]),
                    ];
                } else {
                    $syncData[$permId] = [
                        'department_ids' => null,
                    ];
                }
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
