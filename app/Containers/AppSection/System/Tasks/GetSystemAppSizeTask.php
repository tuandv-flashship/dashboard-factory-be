<?php

namespace App\Containers\AppSection\System\Tasks;

use App\Containers\AppSection\System\Supports\SystemInfo;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\Cache;

final class GetSystemAppSizeTask extends ParentTask
{
    public function __construct(private readonly SystemInfo $systemInfo)
    {
    }

    public function run(): string
    {
        $ttl = (int) config('appSection-system.app_size_cache_seconds', 3600);

        $bytes = $ttl > 0
            ? Cache::remember('system-info.app-size', $ttl, fn () => $this->systemInfo->getAppSize())
            : $this->systemInfo->getAppSize();

        return $this->systemInfo->formatBytes((int) $bytes);
    }
}
