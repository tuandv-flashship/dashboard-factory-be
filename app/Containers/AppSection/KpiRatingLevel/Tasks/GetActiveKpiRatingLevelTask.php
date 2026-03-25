<?php

namespace App\Containers\AppSection\KpiRatingLevel\Tasks;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class GetActiveKpiRatingLevelTask extends ParentTask
{
    private const CACHE_KEY = 'kpi_rating_level_active';
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Find the currently active KPI rating level.
     * Falls back to config default if none found.
     * Result is cached for 5 minutes.
     *
     * @return KpiRatingLevel|array  Model if found in DB, array from config as fallback
     */
    public function run(): KpiRatingLevel|array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $today = Carbon::today();

            $active = KpiRatingLevel::with('details')
                ->where('effective_from', '<=', $today)
                ->where(function ($query) use ($today) {
                    $query->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', $today);
                })
                ->orderBy('effective_from', 'desc')
                ->first();

            if ($active) {
                return $active;
            }

            // Fallback to config default
            return config('appSection-kpiRatingLevel.default');
        });
    }

    /**
     * Clear the cache (call after Create/Update/Delete operations).
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
