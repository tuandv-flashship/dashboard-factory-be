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

            // TODO: Temporarily disabled — auto-create daily shift logic
            // Time-check + dedup guard inside the job itself, enabling
            // dynamic daily_shift_job_at config without scheduler restart.
            // $schedule->job(new CreateDailyShiftJob())
            //     ->everyMinute()
            //     ->timezone(config('app.timezone'))
            //     ->withoutOverlapping()
            //     ->onOneServer();

            // Horizon metrics snapshot (powers dashboard graphs)
            $schedule->command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
        });
    }
}
