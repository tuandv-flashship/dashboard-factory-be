<?php

namespace App\Containers\AppSection\AuditLog\Providers;

use App\Containers\AppSection\AuditLog\Listeners\CustomerLoginListener;
use App\Containers\AppSection\AuditLog\Listeners\CustomerLogoutListener;
use App\Containers\AppSection\AuditLog\Listeners\CustomerRegistrationListener;
use App\Containers\AppSection\AuditLog\Listeners\LoginListener;
use App\Containers\AppSection\AuditLog\Models\AuditHistory;
use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Console\PruneCommand;
use Illuminate\Support\Facades\Event;

final class AuditLogServiceProvider extends ParentServiceProvider
{
    public function boot(): void
    {
        $this->registerAuthListeners();
        $this->registerScheduler();
    }

    private function registerAuthListeners(): void
    {
        Event::listen(Login::class, [LoginListener::class, 'handle']);
        Event::listen(Login::class, [CustomerLoginListener::class, 'handle']);
        Event::listen(Logout::class, [CustomerLogoutListener::class, 'handle']);
        Event::listen(Registered::class, [CustomerRegistrationListener::class, 'handle']);
    }

    private function registerScheduler(): void
    {
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule
                ->command(PruneCommand::class, ['--model' => AuditHistory::class])
                ->dailyAt('00:30');
        });
    }
}
