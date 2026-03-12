<?php

namespace App\Containers\AppSection\Icon\Supports;

use Illuminate\Support\Facades\Cache;

final class IconManager
{
    /** @var array<int, array{name: string, label: string}>|null */
    private ?array $icons = null;

    /**
     * Get all icons from manifest.
     *
     * @return array<int, array{name: string, label: string}>
     */
    public function all(): array
    {
        return $this->icons ??= $this->loadManifest();
    }

    /**
     * Search icons by name.
     *
     * @return array<int, array{name: string, label: string}>
     */
    public function search(?string $keyword = null): array
    {
        $icons = $this->all();

        if ($keyword === null || $keyword === '') {
            return $icons;
        }

        $keyword = mb_strtolower($keyword);

        return array_values(
            array_filter($icons, static fn (array $icon): bool => str_contains($icon['name'], $keyword))
        );
    }

    /**
     * Paginate icons.
     *
     * @param array<int, array{name: string, label: string}> $icons
     * @return array{data: array, meta: array}
     */
    public function paginate(array $icons, int $page = 1, int $perPage = 100): array
    {
        $total = count($icons);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($icons, $offset, $perPage),
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
        ];
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function prefix(): string
    {
        return (string) config('icon.prefix', 'ti ti-');
    }

    /**
     * @return array<int, array{name: string, label: string}>
     */
    private function loadManifest(): array
    {
        $ttl = (int) config('icon.cache_ttl', 1440);

        return Cache::remember('icon_manifest', $ttl * 60, function (): array {
            $path = config('icon.manifest_path');

            if (! $path || ! file_exists($path)) {
                return [];
            }

            $content = file_get_contents($path);
            if ($content === false) {
                return [];
            }

            $data = json_decode($content, true);

            return is_array($data) ? $data : [];
        });
    }

    /**
     * Clear the cached manifest.
     */
    public static function clearCache(): void
    {
        Cache::forget('icon_manifest');
    }
}
