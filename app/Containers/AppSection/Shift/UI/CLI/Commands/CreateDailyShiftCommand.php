<?php

namespace App\Containers\AppSection\Shift\UI\CLI\Commands;

use App\Containers\AppSection\Shift\Actions\CreateDailyShiftAction;
use App\Ship\Parents\Commands\Command as ParentCommand;

/**
 * Artisan command to manually create the default shift 1 for a given date.
 *
 * Usage:
 *   php artisan shift:create-daily
 *   php artisan shift:create-daily --date=2026-04-15
 */
final class CreateDailyShiftCommand extends ParentCommand
{
    protected $signature = 'shift:create-daily
                            {--date= : Target date (Y-m-d). Defaults to today}';

    protected $description = 'Create default shift 1 for a given date from the standard template (Ca 1)';

    public function handle(): int
    {
        $date = $this->option('date');

        if ($date && !strtotime($date)) {
            $this->error("Invalid date format: {$date}");

            return self::FAILURE;
        }

        $this->info("Creating shift 1 for " . ($date ?? 'today') . "...");

        $result = app(CreateDailyShiftAction::class)->run($date);

        match ($result['status']) {
            'created'           => $this->info("✓ {$result['message']}"),
            'inventory_updated' => $this->info("✓ {$result['message']}"),
            'skipped'           => $this->warn("⚠ {$result['message']}"),
            'no_template'       => $this->error("✗ {$result['message']}"),
        };

        return $result['status'] === 'no_template' ? self::FAILURE : self::SUCCESS;
    }
}
