<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            // In local: record everything for full debugging experience
            if ($isLocal) {
                return true;
            }

            // In non-local (fls/pd): only record important entries
            return $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters([
            '_token',
            'password',
            'password_confirmation',
        ]);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'authorization',
        ]);
    }

    /**
     * Configure the Telescope authorization services.
     *
     * Override parent to include 'fls' and 'pd' environments in the
     * Telescope::auth fallback. The parent only checks for 'local',
     * causing 403 when APP_ENV is 'fls' or 'pd'.
     *
     * Mirrors HorizonServiceProvider authorization logic.
     */
    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(function ($request) {
            // Allow access in local and staging environments
            if (app()->environment('local', 'fls')) {
                return true;
            }

            // In production: require admin role
            $user = $request->user('web');

            return $user && $user->hasRole('admin');
        });
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     * Only admin users (role = admin) can view the dashboard.
     *
     * Consistent with HorizonServiceProvider gate logic.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            return $user && $user->hasRole('admin');
        });
    }
}
