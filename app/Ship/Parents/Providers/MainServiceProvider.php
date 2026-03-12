<?php

namespace App\Ship\Parents\Providers;

use Illuminate\Foundation\AliasLoader;

abstract class MainServiceProvider extends ServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    public array $serviceProviders = [];

    /**
     * @var array<string, class-string>
     */
    public array $aliases = [];

    public function register(): void
    {
        parent::register();

        foreach ($this->serviceProviders as $serviceProvider) {
            $this->app->register($serviceProvider);
        }

        if ($this->aliases !== []) {
            $loader = AliasLoader::getInstance();
            foreach ($this->aliases as $alias => $class) {
                $loader->alias($alias, $class);
            }
        }
    }
}
