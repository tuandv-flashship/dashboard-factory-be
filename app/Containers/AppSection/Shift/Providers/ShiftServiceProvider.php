<?php

namespace App\Containers\AppSection\Shift\Providers;

use App\Containers\AppSection\Shift\Jobs\ActivateHourlyRecordsJob;
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
        });
    }
}
