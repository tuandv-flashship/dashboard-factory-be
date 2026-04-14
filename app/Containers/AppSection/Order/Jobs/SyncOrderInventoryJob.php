<?php

namespace App\Containers\AppSection\Order\Jobs;

use App\Containers\AppSection\Order\Tasks\SyncOrderInventoryTask;
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
 * Fetches per-line data (DTF + DTG) and upserts into order_summaries.
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
        app(SyncOrderInventoryTask::class)->run();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[SyncOrderInventory] Job failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
