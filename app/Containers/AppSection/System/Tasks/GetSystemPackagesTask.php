<?php

namespace App\Containers\AppSection\System\Tasks;

use App\Containers\AppSection\System\Supports\SystemInfo;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

final class GetSystemPackagesTask extends ParentTask
{
    public function __construct(private readonly SystemInfo $systemInfo)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $query
     */
    public function run(array $filters = [], array $query = []): LengthAwarePaginator
    {
        $ttl = (int) config('appSection-system.packages_cache_seconds', 300);

        $packages = $ttl > 0
            ? Cache::remember('system-info.packages', $ttl, fn () => $this->buildPackages())
            : $this->buildPackages();

        $total = count($packages);
        $page = max(1, (int) ($filters['page'] ?? LengthAwarePaginator::resolveCurrentPage()));
        $perPage = max(1, (int) ($filters['limit'] ?? config('repository.pagination.limit', 10)));
        $offset = ($page - 1) * $perPage;

        $items = array_slice($packages, $offset, $perPage);

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => $query,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPackages(): array
    {
        $composer = $this->systemInfo->getComposerData();
        $packages = $this->systemInfo->getPackages((array) ($composer['require'] ?? []));

        usort($packages, static fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name']));

        return $packages;
    }
}
