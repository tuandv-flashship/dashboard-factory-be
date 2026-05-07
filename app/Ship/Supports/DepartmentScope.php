<?php

namespace App\Ship\Supports;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

final class DepartmentScope
{
    /** Per-request cache: "userId:permissionFlag" → resolved result. */
    private static array $cache = [];

    /**
     * Resolve department IDs mà user được phép truy cập cho 1 permission.
     *
     * @return int[]|null  null = global, [] = denied, [1,2,3] = scoped
     */
    public static function resolve(Authenticatable $user, string $permissionFlag): ?array
    {
        $key = $user->getAuthIdentifier() . ':' . $permissionFlag;

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        return self::$cache[$key] = self::doResolve($user, $permissionFlag);
    }

    private static function doResolve(Authenticatable $user, string $permissionFlag): ?array
    {
        // SuperAdmin → luôn global
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return null;
        }

        // Eager-load roles + permissions in 1 shot (no-op if already loaded)
        if (method_exists($user, 'loadMissing')) {
            $user->loadMissing('roles.permissions');
        }

        $allDeptIds = [];

        foreach ($user->roles as $role) {
            $rolePerm = $role->permissions->firstWhere('name', $permissionFlag);
            if (!$rolePerm) {
                continue;
            }

            $raw = $rolePerm->pivot->department_ids;

            // NULL = role này cho global access → user global
            if ($raw === null) {
                return null;
            }

            $ids = is_string($raw) ? json_decode($raw, true) : $raw;
            if (is_array($ids)) {
                $allDeptIds = array_merge($allDeptIds, $ids);
            }
        }

        return array_values(array_unique($allDeptIds));
    }

    /**
     * Apply scope filter lên Eloquent query.
     *
     * Dùng trong Tasks/Actions để giới hạn dữ liệu trả về theo department scope.
     */
    public static function applyToQuery(
        Builder $query,
        Authenticatable $user,
        string $permissionFlag,
        string $column = 'department_id',
    ): Builder {
        $ids = self::resolve($user, $permissionFlag);

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /**
     * Kiểm tra user có quyền trên 1 department cụ thể.
     *
     * Dùng trong create/update/delete actions.
     */
    public static function check(
        Authenticatable $user,
        string $permissionFlag,
        int $departmentId,
    ): bool {
        $ids = self::resolve($user, $permissionFlag);

        return $ids === null || in_array($departmentId, $ids, true);
    }

    /** Clear per-request cache (useful in tests). */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
