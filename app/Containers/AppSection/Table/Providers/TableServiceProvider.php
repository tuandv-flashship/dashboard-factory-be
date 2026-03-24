<?php

namespace App\Containers\AppSection\Table\Providers;

use App\Containers\AppSection\Table\Supports\BulkActionRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Registers BulkActionRegistry as singleton — ensures consistent caching
 * across all injections within a request lifecycle.
 */
final class TableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BulkActionRegistry::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(
            __DIR__ . '/../Resources/lang',
            'table'
        );
    }
}
