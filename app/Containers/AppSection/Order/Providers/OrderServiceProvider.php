<?php

namespace App\Containers\AppSection\Order\Providers;

use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;

/**
 * Order container service provider.
 *
 * Note: SyncOrderInventoryJob scheduler is DISABLED.
 * Order inventory sync is now handled by SyncHourlyRecordsTask
 * (Production container) to share the cached allInventory data.
 *
 * SyncOrderInventoryJob still exists for manual resync via API/Command.
 */
final class OrderServiceProvider extends ParentServiceProvider
{
}
