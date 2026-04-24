<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Services\ShiftSchedulerGuard;
use App\Containers\AppSection\Setting\Tasks\GetSettingsTask;
use App\Ship\Parents\Actions\Action as ParentAction;

/**
 * Returns current scheduler settings, merging DB values over config defaults.
 * Covers: SyncHourlyRecordsJob intervals + CreateDailyShiftJob run time.
 */
final class GetProductionSchedulerSettingsAction extends ParentAction
{
    public function __construct(
        private readonly GetSettingsTask $getSettingsTask,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $keys = [
            ...ShiftSchedulerGuard::ALL_SETTING_KEYS,
            'scheduler.daily_shift_job_at',
        ];

        $saved = $this->getSettingsTask->run($keys);

        $defaults = [
            'scheduler' => [
                'in_shift_interval'        => config('factory.hourly_records_sync_interval', 5),
                'off_shift_interval'       => config('factory.off_shift_sync_interval', 15),
                'off_shift_before_minutes' => config('factory.off_shift_before_minutes', 120),
                'off_shift_after_minutes'  => config('factory.off_shift_after_minutes', 180),
                'daily_shift_job_at'       => config('factory.daily_shift_job_at', '00:00'),
            ],
        ];

        return array_replace_recursive($defaults, $saved);
    }
}
