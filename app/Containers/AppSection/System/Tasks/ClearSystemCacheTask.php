<?php

namespace App\Containers\AppSection\System\Tasks;

use App\Containers\AppSection\System\Supports\SystemCache;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class ClearSystemCacheTask extends ParentTask
{
    public function __construct(private readonly SystemCache $systemCache)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(string $type): array
    {
        switch ($type) {
            case 'clear_cms_cache':
                $this->systemCache->clearCmsCache();

                return $this->success($type, 'Application cache cleared.');
            case 'refresh_compiled_views':
                $this->systemCache->clearCompiledViews();

                return $this->success($type, 'Compiled views cleared.');
            case 'clear_config_cache':
                $this->systemCache->clearConfigCache();

                return $this->success($type, 'Config cache cleared.');
            case 'clear_route_cache':
                $this->systemCache->clearRouteCache();

                return $this->success($type, 'Route cache cleared.');
            case 'clear_event_cache':
                $this->systemCache->clearEventCache();

                return $this->success($type, 'Event cache cleared.');
            case 'clear_log':
                $this->systemCache->clearLogs();

                return $this->success($type, 'Log files cleared.');
            case 'clear_all_cache':
                $this->systemCache->clearAll();

                return $this->success($type, 'All caches cleared.');
            case 'optimize':
                return $this->systemCache->runOptimization() + ['type' => $type];
            case 'clear_optimize':
                return $this->systemCache->clearOptimization() + ['type' => $type];
            default:
                return [
                    'type' => $type,
                    'success' => false,
                    'message' => 'Unsupported cache action.',
                ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function success(string $type, string $message): array
    {
        return [
            'type' => $type,
            'success' => true,
            'message' => $message,
        ];
    }
}
