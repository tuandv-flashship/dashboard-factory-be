<?php

namespace App\Containers\AppSection\Authorization\UI\API\Transformers;

use App\Containers\AppSection\Authorization\Models\Role;

final class RoleAdminTransformer extends RoleTransformer
{
    public function transform(Role $role): array
    {
        $result = parent::transform($role);
        $result['guard_name'] = $role->guard_name;
        $result['permission_scopes'] = $this->buildScopes($role);

        return $result;
    }

    private function buildScopes(Role $role): array
    {
        if (!$role->relationLoaded('permissions')) {
            return [];
        }

        $scopes = [];

        foreach ($role->permissions as $perm) {
            $raw = $perm->pivot->department_ids;
            if ($raw === null) {
                continue;
            }

            $ids = is_string($raw) ? json_decode($raw, true) : $raw;
            if (!is_array($ids) || $ids === []) {
                continue;
            }

            $scopes[] = [
                'permission_id'  => $perm->getHashedKey(),
                'department_ids' => array_map(fn ($id) => $this->hashId($id), $ids),
            ];
        }

        return $scopes;
    }
}
