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
        // Skip setup entirely when Telescope is disabled (zero overhead)
        if (! config('telescope.enabled')) {
            return;
        }

        Telescope::night();

        $this->hideSensitiveRequestDetails();

        // Record everything only when explicitly requested (TELESCOPE_RECORD_ALL=true).
        // Recording every query/request on the realtime dashboard produced ~720 MB of
        // telescope_entries per factory in just 48 hours on the dev servers.
        $recordAll = (bool) config('telescope.record_all', false);

        Telescope::filter(function (IncomingEntry $entry) use ($recordAll) {
            if ($recordAll) {
                return true;
            }

            // Default (all environments): only record important entries
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
            // Local: no restrictions
            if (app()->environment('local')) {
                return true;
            }

            // All other environments: require admin role
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
