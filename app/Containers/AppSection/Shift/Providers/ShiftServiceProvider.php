<?php

namespace App\Containers\AppSection\Shift\Providers;

use App\Containers\AppSection\Shift\Jobs\CreateDailyShiftJob;
use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

final class ShiftServiceProvider extends ParentServiceProvider
{
    public function boot(): void
    {
        $this->registerScheduler();
    }

    private function registerScheduler(): void
    {
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {

            // Auto-create shift 1 & refresh inventory before shift starts
            $schedule->job(new CreateDailyShiftJob())
                ->dailyAt(config('factory.daily_shift_job_at', '05:50'))
                ->timezone(config('app.timezone'))
                ->withoutOverlapping();

            // Horizon metrics snapshot (powers dashboard graphs)
            $schedule->command('horizon:snapshot')->everyFiveMinutes();
        });
    }
}
