<?php

namespace App\Containers\AppSection\Media\Storage\BunnyCDN;

final class Util
{
    public static function splitPathIntoDirectoryAndFile(string $path): array
    {
        $path = self::endsWith($path, '/') ? substr($path, 0, -1) : $path;
        $parts = explode('/', $path);
        $file = array_pop($parts);
        $directory = implode('/', $parts);

        return [
            'file' => $file,
            'dir' => $directory,
        ];
    }

    public static function normalizePath(string $path, bool $isDirectory = false): string
    {
        $path = str_replace('\\', '/', $path);

        if ($isDirectory && ! self::endsWith($path, '/')) {
            $path .= '/';
        }

        while (str_contains($path, '//')) {
            $path = str_replace('//', '/', $path);
        }

        if (str_starts_with($path, '/')) {
            $path = substr($path, 1);
        }

        return $path;
    }

    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }

        return substr($haystack, -$length) === $needle;
    }
}
