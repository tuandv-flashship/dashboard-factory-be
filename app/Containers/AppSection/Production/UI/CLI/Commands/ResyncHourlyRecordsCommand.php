<?php

namespace App\Containers\AppSection\Production\UI\CLI\Commands;

use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Production\Tasks\SyncHourlyRecordsTask;
use App\Ship\Parents\Commands\Command as ParentCommand;
use Illuminate\Support\Facades\Cache;

/**
 * Artisan command to manually resync hourly records from FPlatform.
 *
 * Usage:
 *   php artisan production:resync
 *   php artisan production:resync --date=2026-04-14 --shift=1
 */
final class ResyncHourlyRecordsCommand extends ParentCommand
{
    protected $signature = 'production:resync
                            {--date= : Target date (Y-m-d). Defaults to today}
                            {--shift= : Shift number (1, 2, ...). Defaults to latest active}
                            {--shift-detail= : Shift detail ID to resync. Omit to sync all departments}';

    protected $description = 'Manually resync hourly records (actual, staff, inventory, efficiency) from FPlatform';

    public function handle(): int
    {
        $date          = $this->option('date');
        $shift         = $this->option('shift') ? (int) $this->option('shift') : null;
        $shiftDetailId = $this->option('shift-detail') ? (int) $this->option('shift-detail') : null;

        if ($date && !strtotime($date)) {
            $this->error("Invalid date format: {$date}");

            return self::FAILURE;
        }

        $label = ($date ?? 'today')
            . ($shift ? " shift {$shift}" : '')
            . ($shiftDetailId ? " [shift_detail #{$shiftDetailId}]" : '');
        $this->info("Resyncing hourly records for {$label}...");

        $result = app(SyncHourlyRecordsTask::class)->run($date, $shift, $shiftDetailId);

        if (!$result['shift']) {
            $this->error("✗ {$result['message']}");

            return self::FAILURE;
        }

        // Clear cached hourly response
        $resolvedDate = $result['shift']->date->toDateString();
        $resolvedShift = $result['shift']->shift_number;
        Cache::forget(ProductionCacheKeys::allLinesHourly($resolvedDate, $resolvedShift));

        if ($result['synced'] > 0) {
            $this->info("✓ {$result['message']}");
        } else {
            $this->warn("⚠ {$result['message']}");
        }

        return self::SUCCESS;
    }
}
