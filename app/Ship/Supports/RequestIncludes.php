<?php

namespace App\Ship\Supports;

final class RequestIncludes
{
    /**
     * @return array<int, string>
     */
    public static function parse(?string $include): array
    {
        if ($include === null) {
            return [];
        }

        $include = trim($include);
        if ($include === '') {
            return [];
        }

        $items = array_map('trim', explode(',', $include));
        $items = array_filter($items, static fn (string $item): bool => $item !== '');
        $items = array_values(array_unique($items));

        return $items;
    }

    public static function has(?string $include, string $needle): bool
    {
        return in_array($needle, self::parse($include), true);
    }
}

