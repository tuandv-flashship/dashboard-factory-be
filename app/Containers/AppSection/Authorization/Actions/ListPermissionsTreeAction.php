<?php

namespace App\Containers\AppSection\Authorization\Actions;

use App\Ship\Parents\Actions\Action as ParentAction;
use App\Ship\Supports\PermissionRegistry;
use App\Containers\AppSection\Authorization\Models\Permission;
use Illuminate\Support\Facades\Cache;

final class ListPermissionsTreeAction extends ParentAction
{
    public function run(string|null $guard = null): array
    {
        $guard = $guard ?: auth()->activeGuard() ?: 'api';
        $cacheKey = 'permissions.tree.' . ($guard ?: 'all');

        return Cache::rememberForever($cacheKey, function () use ($guard): array {
            $tree = PermissionRegistry::tree();

            if ($guard === null) {
                return $this->attachIds($tree, [], true);
            }

            $tree = $this->filterByGuard($tree, $guard);

            return $this->attachIds($tree, $this->loadIdsByName($guard), true);
        });
    }

    private function filterByGuard(array $nodes, string $guard): array
    {
        $filtered = [];

        foreach ($nodes as $node) {
            $children = $this->filterByGuard($node['children'] ?? [], $guard);
            $guards = $node['guards'] ?? null;
            $allowed = $guards === null || in_array($guard, $guards, true);

            if ($allowed || $children !== []) {
                $node['children'] = $children;
                $filtered[] = $node;
            }
        }

        return $filtered;
    }

    private function loadIdsByName(string $guard): array
    {
        $permissions = Permission::query()
            ->where('guard_name', $guard)
            ->get(['id', 'name']);

        $map = [];
        foreach ($permissions as $permission) {
            $map[$permission->name] = $permission->getHashedKey();
        }

        return $map;
    }

    private function attachIds(array $nodes, array $idByName, bool $pruneMissing): array
    {
        $withIds = [];

        foreach ($nodes as $node) {
            unset($node['guards']);
            $node['id'] = $idByName[$node['flag']] ?? null;
            $node['children'] = $this->attachIds($node['children'] ?? [], $idByName, $pruneMissing);

            if ($pruneMissing && $node['id'] === null && $node['children'] === []) {
                continue;
            }

            $withIds[] = $node;
        }

        return $withIds;
    }
}
