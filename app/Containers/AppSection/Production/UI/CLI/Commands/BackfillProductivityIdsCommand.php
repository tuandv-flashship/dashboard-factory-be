<?php

namespace App\Containers\AppSection\Production\UI\CLI\Commands;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use Illuminate\Console\Command;

/**
 * One-time backfill: stamp `_id` onto each item in existing productivity_json.
 *
 * Historical records that are no longer synced by the cron job need this
 * manual backfill so that productivity_item_id validation works on issues.
 *
 * Safe to run multiple times — idempotent (items that already have _id
 * will get the same deterministic value re-stamped).
 *
 * Usage: php artisan production:backfill-productivity-ids
 */
final class BackfillProductivityIdsCommand extends Command
{
    protected $signature = 'production:backfill-productivity-ids
                            {--dry-run : Show counts without modifying data}';

    protected $description = 'Stamp _id onto each item in existing productivity_json records';

    public function handle(): int
    {
        $query = HourlyRecord::whereNotNull('productivity_json');
        $total = $query->count();

        if ($total === 0) {
            $this->info('No records with productivity_json found.');

            return self::SUCCESS;
        }

        $this->info("Found {$total} records with productivity_json.");

        if ($this->option('dry-run')) {
            // Count how many actually need stamping (missing _id in any item)
            $needsStamp = 0;
            $query->chunkById(500, function ($records) use (&$needsStamp) {
                foreach ($records as $record) {
                    $items = $record->productivity_json;
                    if (collect($items)->contains(fn ($item) => !isset($item['_id']))) {
                        $needsStamp++;
                    }
                }
            });

            $this->info("[dry-run] {$needsStamp} / {$total} records need _id backfill.");

            return self::SUCCESS;
        }

        $updated = 0;
        $bar = $this->output->createProgressBar($total);

        $query->chunkById(500, function ($records) use (&$updated, $bar) {
            foreach ($records as $record) {
                $stamped = HourlyRecord::stampItemIds($record->productivity_json);

                if ($stamped !== null && $stamped !== $record->productivity_json) {
                    $record->update(['productivity_json' => $stamped]);
                    $updated++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Updated {$updated} / {$total} records.");

        return self::SUCCESS;
    }
}
