<?php

namespace App\Containers\AppSection\Production\Providers;

use App\Containers\AppSection\Production\Jobs\SyncHourlyRecordsJob;
use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

final class ProductionServiceProvider extends ParentServiceProvider
{
    public function boot(): void
    {
        $this->registerScheduler();
    }

    private function registerScheduler(): void
    {
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $interval = (int) config('factory.hourly_records_sync_interval', 5);

            // Interval = 0 → disabled
            if ($interval <= 0) {
                return;
            }

            $schedule->job(new SyncHourlyRecordsJob())
                ->cron("*/{$interval} * * * *")
                ->withoutOverlapping()
                ->onOneServer();
        });
    }
}
