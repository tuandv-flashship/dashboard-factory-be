<?php

namespace App\Containers\AppSection\Production\Providers;

use App\Containers\AppSection\Production\Jobs\EndOfDaySyncJob;
use App\Containers\AppSection\Production\Jobs\FinalizeShiftRecordsJob;
use App\Containers\AppSection\Production\Jobs\SyncHourlyRecordsJob;
use App\Containers\AppSection\Production\Services\ShiftSchedulerGuard;
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
            $schedule->job(new SyncHourlyRecordsJob())
                ->everyMinute()
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->job(new FinalizeShiftRecordsJob())
                ->dailyAt('01:00')
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->job(new EndOfDaySyncJob())
                ->dailyAt(app(ShiftSchedulerGuard::class)->endOfDaySyncAt())
                ->withoutOverlapping()
                ->onOneServer();
        });
    }
}
