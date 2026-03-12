<?php

namespace App\Containers\AppSection\RequestLog\Providers;

use App\Containers\AppSection\RequestLog\Models\RequestLog;
use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Console\PruneCommand;

final class RequestLogServiceProvider extends ParentServiceProvider
{
    public function boot(): void
    {
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule
                ->command(PruneCommand::class, ['--model' => RequestLog::class])
                ->dailyAt('00:30');
        });
    }
}
