<?php

namespace App\Containers\AppSection\Order\Jobs;

use App\Containers\AppSection\Order\Tasks\SyncOrderInventoryTask;
use App\Containers\AppSection\Shift\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled Job — syncs order inventory (tồn đơn hàng) from Fplatform.
 *
 * Runs every N minutes (configurable via ORDER_INVENTORY_SYNC_INTERVAL).
 * Only executes during active shift hours — skips entirely outside
 * shift time window to avoid unnecessary FPlatform queries.
 *
 * Manual resync via API/Command bypasses this guard.
 *
 * Idempotent: safe to run multiple times (uses updateOrCreate).
 */
final class SyncOrderInventoryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $shift = Shift::current();

        if (!$shift || !$shift->isWithinTimeWindow()) {
            return;
        }

        app(SyncOrderInventoryTask::class)->run();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[SyncOrderInventory] Job failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
