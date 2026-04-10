<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
        // Horizon::routeMailNotificationsTo('admin@example.com');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * Only admin users (role = admin) can view the dashboard.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            // In local environment, always allow access
            if (app()->environment('local', 'fls', 'pd')) {
                return true;
            }

            // In production, restrict to admin users
            return $user && $user->hasRole('admin');
        });
    }
}
