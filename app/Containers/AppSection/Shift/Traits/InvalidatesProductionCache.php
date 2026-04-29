<?php

namespace App\Containers\AppSection\Shift\Traits;

use App\Containers\AppSection\Production\Support\ProductionCacheKeys;

/**
 * Invalidate production dashboard cache after hourly record changes.
 *
 * Used by Create/Update/Delete hourly record controllers to ensure
 * the API returns fresh data for historical shifts (cached for 1 hour).
 */
trait InvalidatesProductionCache
{
    private function invalidateProductionCache(int $shiftId, int $departmentId): void
    {
        ProductionCacheKeys::flushForDepartment($shiftId, $departmentId);
    }
}
