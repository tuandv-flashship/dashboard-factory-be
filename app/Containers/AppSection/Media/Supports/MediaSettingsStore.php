<?php

namespace App\Containers\AppSection\Media\Supports;

use App\Containers\AppSection\Setting\Models\Setting;
use Illuminate\Support\Facades\Cache;

class MediaSettingsStore
{
    private const CACHE_KEY = 'media.settings';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return $this->load();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return $settings[$key] ?? $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return array<int, mixed>
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $default;
    }

    public function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        $defaults = (array) config('media.settings_defaults', []);
        $defaults = array_merge($defaults, $this->resolveDefaultSizes());

        try {
            $rows = Setting::query()
                ->where(function ($query): void {
                    $query->where('key', 'like', 'media_%')
                        ->orWhere('key', 'user_can_only_view_own_media');
                })
                ->get(['key', 'value']);

            $values = [];
            foreach ($rows as $row) {
                $values[$row->key] = $row->value;
            }

            return array_replace($defaults, $values);
        } catch (\Throwable) {
            return $defaults;
        }
    }

    /**
     * @return array<string, int>
     */
    private function resolveDefaultSizes(): array
    {
        $defaults = [];
        $sizes = (array) config('media.sizes', []);

        foreach ($sizes as $name => $size) {
            $parts = explode('x', strtolower((string) $size));
            if (count($parts) !== 2) {
                continue;
            }

            $defaults[sprintf('media_sizes_%s_width', $name)] = (int) $parts[0];
            $defaults[sprintf('media_sizes_%s_height', $name)] = (int) $parts[1];
        }

        return $defaults;
    }
}
