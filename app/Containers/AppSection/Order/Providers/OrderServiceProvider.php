<?php

namespace App\Containers\AppSection\Order\Providers;

use App\Containers\AppSection\Order\Jobs\SyncOrderInventoryJob;
use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

final class OrderServiceProvider extends ParentServiceProvider
{
    public function boot(): void
    {
        $this->registerScheduler();
    }

    private function registerScheduler(): void
    {
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $interval = (int) config('factory.order_inventory_sync_interval', 1);

            // Interval = 0 → disabled
            if ($interval <= 0) {
                return;
            }

            $schedule->job(new SyncOrderInventoryJob())
                ->cron("*/{$interval} * * * *")
                ->withoutOverlapping()
                ->onOneServer();
        });
    }
}
