<?php

namespace App\Ship\Supports;

final class SystemCommandRegistry
{
    public static function allowedActions(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array{command: string, options: array<string, mixed>}|null
     */
    public static function find(string $action): ?array
    {
        $normalized = strtolower(trim($action));

        $definitions = self::definitions();

        return $definitions[$normalized] ?? null;
    }

    /**
     * @return array{command: string, options: array<string, mixed>}|null
     */
    public static function resolve(string $action): ?array
    {
        return self::find($action);
    }

    private static function definitions(): array
    {
        $definitions = config('system-commands.commands', []);

        return is_array($definitions) ? $definitions : [];
    }
}
