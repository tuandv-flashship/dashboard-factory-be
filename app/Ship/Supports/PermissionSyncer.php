<?php

namespace App\Ship\Supports;

use App\Containers\AppSection\Authorization\Models\Permission;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

final class PermissionSyncer
{
    public function __construct(
        private readonly PermissionRegistrar $registrar,
    ) {
    }

    public function sync(array $guards = [], bool $includeWeb = false, bool $prune = false): array
    {
        $guards = $this->resolveGuards($guards, $includeWeb);
        $permissions = PermissionRegistry::all();

        $rowsByKey = [];
        $expectedByGuard = [];

        foreach ($permissions as $permission) {
            $name = strtolower($permission['flag']);
            $displayName = $permission['display_name'] ?? $permission['name'] ?? null;
            $description = $permission['description'] ?? null;

            $permissionGuards = $permission['guards'] ?? null;
            if (empty($permissionGuards)) {
                $permissionGuards = $guards;
            }

            foreach ($permissionGuards as $guard) {
                if (!in_array($guard, $guards, true)) {
                    continue;
                }

                $key = $guard . ':' . $name;
                $rowsByKey[$key] = [
                    'name' => $name,
                    'guard_name' => $guard,
                    'display_name' => $displayName,
                    'description' => $description,
                ];
                $expectedByGuard[$guard][$name] = true;
            }
        }

        $created = 0;
        $updated = 0;

        foreach ($rowsByKey as $row) {
            $permission = Permission::query()->updateOrCreate(
                [
                    'name' => $row['name'],
                    'guard_name' => $row['guard_name'],
                ],
                $row,
            );

            if ($permission->wasRecentlyCreated) {
                $created++;
            } elseif ($permission->wasChanged()) {
                $updated++;
            }
        }

        $pruned = 0;
        if ($prune && $expectedByGuard !== []) {
            foreach ($guards as $guard) {
                if (!isset($expectedByGuard[$guard])) {
                    continue;
                }

                $names = array_keys($expectedByGuard[$guard]);
                $pruned += Permission::query()
                    ->where('guard_name', $guard)
                    ->whereNotIn('name', $names)
                    ->delete();
            }
        }

        $this->registrar->forgetCachedPermissions();
        $this->forgetPermissionTreeCache($guards);

        $total = count($rowsByKey);
        $skipped = $total - $created - $updated;

        return [
            'guards' => $guards,
            'total' => $total,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'pruned' => $pruned,
        ];
    }

    private function resolveGuards(array $guards, bool $includeWeb): array
    {
        if ($guards === []) {
            $guards = array_keys(config('auth.guards', []));
        }

        $guards = array_values(array_unique(array_filter(
            $guards,
            static fn ($guard): bool => is_string($guard) && $guard !== '',
        )));

        if (!$includeWeb) {
            $guards = array_values(array_filter(
                $guards,
                static fn (string $guard): bool => $guard !== 'web',
            ));
        }

        return $guards;
    }

    private function forgetPermissionTreeCache(array $guards): void
    {
        Cache::forget('permissions.tree.all');

        foreach ($guards as $guard) {
            Cache::forget('permissions.tree.' . $guard);
        }
    }
}
