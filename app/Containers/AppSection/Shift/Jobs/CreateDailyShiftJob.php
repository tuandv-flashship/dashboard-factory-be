<?php

namespace App\Containers\AppSection\Shift\Jobs;

use App\Containers\AppSection\Shift\Actions\CreateDailyShiftAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled Job — runs daily before shift starts (default 05:50, configurable via DAILY_SHIFT_JOB_AT).
 *
 * Auto-creates shift 1 for today from the default template (Ca 1).
 * If shift already exists, refreshes day_start_inventory from Fplatform.
 *
 * Fetches tồn đầu ngày from Fplatform, auto-selects all active machines
 * for per_machine departments.
 *
 * Idempotent: safe to run multiple times.
 */
final class CreateDailyShiftJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $result = app(CreateDailyShiftAction::class)->run();

        match ($result['status']) {
            'created'           => Log::info("[CreateDailyShift] {$result['message']}"),
            'inventory_updated' => Log::info("[CreateDailyShift] {$result['message']}"),
            'skipped'           => Log::info("[CreateDailyShift] {$result['message']}"),
            'no_template'       => Log::warning("[CreateDailyShift] {$result['message']}"),
        };
    }
}
