<?php

namespace App\Containers\AppSection\System\Supports;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

final class SystemCache
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Application $app,
    ) {
    }

    public function clearCmsCache(): void
    {
        Cache::flush();
    }

    public function clearCompiledViews(): void
    {
        $compiledPath = config('view.compiled');
        if (! $compiledPath || ! $this->files->isDirectory($compiledPath)) {
            return;
        }

        foreach ($this->files->glob($compiledPath . '/*.php') as $view) {
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($view, true);
            }

            $this->files->delete($view);
        }
    }

    public function clearConfigCache(): void
    {
        $this->files->delete($this->app->getCachedConfigPath());
    }

    public function clearRouteCache(): void
    {
        foreach ($this->files->glob($this->app->bootstrapPath('cache/*')) as $cacheFile) {
            if (Str::contains($cacheFile, 'routes')) {
                $this->files->delete($cacheFile);
            }
        }
    }

    public function clearEventCache(): void
    {
        $this->files->delete($this->app->bootstrapPath('cache/events.php'));
    }

    public function clearLogs(): void
    {
        if (! $this->files->isDirectory($logPath = storage_path('logs'))) {
            return;
        }

        foreach ($this->files->glob($logPath . '/*.log') as $file) {
            $this->files->delete($file);
        }
    }

    public function clearAll(): void
    {
        $this->clearCmsCache();
        $this->clearCompiledViews();
        $this->clearRouteCache();
        $this->clearConfigCache();
        $this->clearEventCache();
        $this->clearLogs();
        $this->clearBootstrapCache();
    }

    public function runOptimization(): array
    {
        $results = [
            'success' => true,
            'message' => 'Optimization completed.',
            'details' => [],
        ];

        $this->clearConfigCache();
        $this->clearRouteCache();
        $this->clearCompiledViews();

        try {
            Artisan::call('config:cache');
            $results['details'][] = 'Config cached.';
        } catch (\Throwable $exception) {
            $results['success'] = false;
            $results['details'][] = 'Config cache failed: ' . $exception->getMessage();
        }

        try {
            Artisan::call('view:cache');
            $results['details'][] = 'Views cached.';
        } catch (\Throwable $exception) {
            $results['success'] = false;
            $results['details'][] = 'View cache failed: ' . $exception->getMessage();
        }

        $this->clearCmsCache();

        if (! $results['success']) {
            $results['message'] = 'Optimization failed.';
        }

        return $results;
    }

    public function clearOptimization(): array
    {
        $results = [
            'success' => true,
            'message' => 'Optimization cache cleared.',
            'details' => [],
        ];

        try {
            Artisan::call('config:clear');
            $results['details'][] = 'Config cache cleared.';
        } catch (\Throwable $exception) {
            $results['success'] = false;
            $results['details'][] = 'Config cache clear failed: ' . $exception->getMessage();
        }

        try {
            Artisan::call('route:clear');
            $results['details'][] = 'Route cache cleared.';
        } catch (\Throwable $exception) {
            $results['success'] = false;
            $results['details'][] = 'Route cache clear failed: ' . $exception->getMessage();
        }

        try {
            Artisan::call('view:clear');
            $results['details'][] = 'View cache cleared.';
        } catch (\Throwable $exception) {
            $results['success'] = false;
            $results['details'][] = 'View cache clear failed: ' . $exception->getMessage();
        }

        try {
            Artisan::call('event:clear');
            $results['details'][] = 'Event cache cleared.';
        } catch (\Throwable $exception) {
            $results['success'] = false;
            $results['details'][] = 'Event cache clear failed: ' . $exception->getMessage();
        }

        if (! $results['success']) {
            $results['message'] = 'Optimization cache clear failed.';
        }

        return $results;
    }

    public function getCacheSize(): int
    {
        $cachePath = storage_path('framework/cache');
        if (! File::isDirectory($cachePath)) {
            return 0;
        }

        return $this->calculateDirectorySize($cachePath);
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, 2) . ' ' . $units[$power];
    }

    private function clearBootstrapCache(): void
    {
        foreach ($this->files->glob($this->app->bootstrapPath('cache/*.php')) as $cacheFile) {
            $this->files->delete($cacheFile);
        }
    }

    private function calculateDirectorySize(string $directory): int
    {
        $size = 0;

        foreach (File::glob(rtrim($directory, '/') . '/*', GLOB_NOSORT) as $each) {
            $size += File::isFile($each) ? File::size($each) : $this->calculateDirectorySize($each);
        }

        return $size;
    }
}
