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
     * Configure the Horizon authorization services.
     *
     * Override parent to include 'fls' and 'pd' environments in the
     * Horizon::auth fallback. The parent only checks for 'local',
     * causing 403 when APP_ENV is 'fls' or 'pd'.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(function ($request) {
            if (app()->environment('local', 'fls', 'pd')) {
                return true;
            }

            $user = $request->user('web');

            return $user && $user->hasRole('admin');
        });
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * Only admin users (role = admin) can view the dashboard.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            return $user && $user->hasRole('admin');
        });
    }
}
