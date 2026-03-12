<?php

namespace App\Containers\AppSection\System\Tasks;

use App\Containers\AppSection\System\Supports\SystemCache;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class GetSystemCacheStatusTask extends ParentTask
{
    public function __construct(private readonly SystemCache $systemCache)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $bytes = $this->systemCache->getCacheSize();

        return [
            'cache_size_bytes' => $bytes,
            'cache_size' => $this->systemCache->formatBytes($bytes),
            'types' => $this->types(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function types(): array
    {
        return [
            [
                'type' => 'clear_cms_cache',
                'label' => 'Clear application cache',
            ],
            [
                'type' => 'refresh_compiled_views',
                'label' => 'Clear compiled views',
            ],
            [
                'type' => 'clear_config_cache',
                'label' => 'Clear config cache',
            ],
            [
                'type' => 'clear_route_cache',
                'label' => 'Clear route cache',
            ],
            [
                'type' => 'clear_event_cache',
                'label' => 'Clear event cache',
            ],
            [
                'type' => 'clear_log',
                'label' => 'Clear log files',
            ],
            [
                'type' => 'clear_all_cache',
                'label' => 'Clear all caches',
            ],
            [
                'type' => 'optimize',
                'label' => 'Optimize application',
            ],
            [
                'type' => 'clear_optimize',
                'label' => 'Clear optimization cache',
            ],
        ];
    }
}
