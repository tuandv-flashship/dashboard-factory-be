<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Services\ShiftSchedulerGuard;
use App\Containers\AppSection\Setting\Tasks\UpsertSettingsTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\Cache;

/**
 * Persists scheduler settings to the settings table and immediately
 * invalidates all related cache keys so changes take effect within
 * the next scheduler tick — no restart required.
 */
final class UpdateProductionSchedulerSettingsAction extends ParentAction
{
    public function __construct(
        private readonly UpsertSettingsTask $upsertSettingsTask,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function run(array $data): void
    {
        $this->upsertSettingsTask->run(['scheduler' => $data]);

        $this->invalidateCache($data);
    }

    /** @param array<string, mixed> $data */
    private function invalidateCache(array $data): void
    {
        // Invalidate ShiftSchedulerGuard setting cache
        foreach (ShiftSchedulerGuard::ALL_SETTING_KEYS as $key) {
            Cache::forget(ShiftSchedulerGuard::SETTING_CACHE_PREFIX . $key);
        }

        // Invalidate CreateDailyShiftJob target-time cache if updated
        if (isset($data['daily_shift_job_at'])) {
            Cache::forget(ShiftSchedulerGuard::SETTING_CACHE_PREFIX . 'scheduler.daily_shift_job_at');
        }
    }
}
