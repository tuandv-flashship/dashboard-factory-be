<?php

namespace App\Containers\AppSection\Icon\Providers;

use App\Containers\AppSection\Icon\Commands\SyncIconManifestCommand;
use App\Containers\AppSection\Icon\Supports\IconManager;
use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;

final class IconServiceProvider extends ParentServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IconManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncIconManifestCommand::class,
            ]);
        }
    }
}
