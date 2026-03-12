<?php

namespace App\Ship\Supports;

use Illuminate\Support\Facades\Cache;

final class SystemCommandStore
{
    private const KEY_PREFIX = 'system_commands.job.';

    /**
     * @param array<string, mixed> $payload
     */
    public static function put(string $jobId, array $payload): void
    {
        $ttl = self::ttlSeconds();

        Cache::put(self::key($jobId), $payload, now()->addSeconds($ttl));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $jobId): ?array
    {
        $payload = Cache::get(self::key($jobId));

        return is_array($payload) ? $payload : null;
    }

    public static function key(string $jobId): string
    {
        return self::KEY_PREFIX . $jobId;
    }

    private static function ttlSeconds(): int
    {
        $ttl = (int) config('system-commands.result_ttl', 600);

        return $ttl > 0 ? $ttl : 600;
    }
}
