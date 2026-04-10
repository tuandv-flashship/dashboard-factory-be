<?php

namespace App\Containers\AppSection\Shift\Providers;

use App\Containers\AppSection\Shift\Jobs\ActivateHourlyRecordsJob;
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
            // Run at :00 of every hour (first second)
            $schedule->job(new ActivateHourlyRecordsJob())->hourly();

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
