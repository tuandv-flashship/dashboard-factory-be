<?php

namespace App\Ship\Supports;

final class PermissionRegistry
{
    public static function all(): array
    {
        return self::normalizeList(config('permissions', []));
    }

    public static function groupedByContainer(): array
    {
        $groups = [];

        foreach (self::containerPermissions() as $container) {
            $groups[$container['key']] = [
                'section' => $container['section'],
                'container' => $container['container'],
                'permissions' => self::buildTree(
                    self::normalizeList($container['permissions']),
                ),
            ];
        }

        ksort($groups);

        return $groups;
    }

    public static function tree(): array
    {
        return self::buildTree(self::all());
    }

    private static function containerPermissions(): array
    {
        $paths = glob(base_path('app/Containers/*/*/Configs/permissions.php')) ?: [];

        $containers = [];
        foreach ($paths as $path) {
            $permissions = require $path;
            if (!is_array($permissions)) {
                continue;
            }

            $container = self::parseContainerPath($path);
            if ($container === null) {
                continue;
            }

            $containers[] = [
                'section' => $container['section'],
                'container' => $container['container'],
                'key' => $container['key'],
                'permissions' => $permissions,
            ];
        }

        return $containers;
    }

    private static function parseContainerPath(string $path): array|null
    {
        $parts = explode(DIRECTORY_SEPARATOR, str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
        $index = array_search('Containers', $parts, true);
        if ($index === false || !isset($parts[$index + 1], $parts[$index + 2])) {
            return null;
        }

        $section = $parts[$index + 1];
        $container = $parts[$index + 2];

        return [
            'section' => $section,
            'container' => $container,
            'key' => $section . '.' . $container,
        ];
    }

    private static function normalizeList(array $permissions): array
    {
        $normalized = [];
        self::collect($permissions, $normalized);

        return array_values($normalized);
    }

    private static function collect(array $permissions, array &$normalized): void
    {
        foreach ($permissions as $key => $permission) {
            if (!is_array($permission)) {
                continue;
            }

            if (isset($permission['flag'])) {
                $entry = self::normalizeEntry($permission);
                if ($entry !== null) {
                    $normalized[$entry['flag']] = $entry;
                }
                continue;
            }

            if (is_string($key) && isset($permission['name'])) {
                $permission['flag'] = $key;
                $entry = self::normalizeEntry($permission);
                if ($entry !== null) {
                    $normalized[$entry['flag']] = $entry;
                }
                continue;
            }

            self::collect($permission, $normalized);
        }
    }

    private static function normalizeEntry(array $permission): array|null
    {
        $flag = trim((string) ($permission['flag'] ?? ''));
        if ($flag === '') {
            return null;
        }

        $flag = strtolower($flag);
        $displayName = $permission['display_name'] ?? $permission['name'] ?? null;
        $description = $permission['description'] ?? null;
        if ($description === null && $displayName !== null) {
            $description = $displayName;
        }
        $parentFlag = $permission['parent_flag'] ?? null;
        if (is_string($parentFlag)) {
            $parentFlag = trim($parentFlag);
            $parentFlag = $parentFlag !== '' ? strtolower($parentFlag) : null;
        } else {
            $parentFlag = null;
        }

        return [
            'flag' => $flag,
            'name' => $flag,
            'display_name' => $displayName,
            'description' => $description,
            'guards' => self::normalizeGuards($permission['guards'] ?? $permission['guard'] ?? null),
            'parent_flag' => $parentFlag,
        ];
    }

    private static function normalizeGuards(mixed $guards): array|null
    {
        if ($guards === null) {
            return ['api'];
        }

        if (is_string($guards)) {
            $guards = [$guards];
        }

        if (!is_array($guards)) {
            return null;
        }

        $normalized = [];
        foreach ($guards as $guard) {
            if (!is_string($guard)) {
                continue;
            }

            $guard = trim($guard);
            if ($guard === '') {
                continue;
            }

            $normalized[] = $guard;
        }

        if ($normalized === []) {
            return null;
        }

        return array_values(array_unique($normalized));
    }

    private static function buildTree(array $permissions): array
    {
        $nodes = [];
        foreach ($permissions as $permission) {
            $permission['children'] = [];
            $nodes[$permission['flag']] = $permission;
        }

        $roots = [];
        foreach ($nodes as $flag => &$permission) {
            $parent = $permission['parent_flag'] ?? null;
            if (is_string($parent) && $parent !== '' && isset($nodes[$parent])) {
                $nodes[$parent]['children'][] = &$permission;
                continue;
            }

            $roots[] = &$permission;
        }
        unset($permission);

        return $roots;
    }
}
