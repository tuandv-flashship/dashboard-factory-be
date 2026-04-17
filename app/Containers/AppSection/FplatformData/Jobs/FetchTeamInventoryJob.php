<?php

namespace App\Containers\AppSection\FplatformData\Jobs;

use App\Containers\AppSection\FplatformData\Actions\GetDailyInventoryAction;
use App\Containers\AppSection\FplatformData\Enums\Team;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Fetch inventory for a single team and cache the result.
 *
 * Dispatched in parallel via Bus::batch() from GetAllTeamsInventoryTask.
 * Each job runs 1 FPlatform query and caches the result individually.
 *
 * Cache key: fplatform:team-inventory:{team}:{date}
 */
final class FetchTeamInventoryJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Cache TTL for historical dates (seconds) */
    private const CACHE_TTL_HISTORICAL = 3600;

    /** Cache TTL for today's date (seconds) */
    private const CACHE_TTL_TODAY = 300;

    public function __construct(
        private readonly string $teamValue,
        private readonly string $date,
    ) {
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $team = Team::from($this->teamValue);

        try {
            $result = app(GetDailyInventoryAction::class)->run($this->date, $team);
        } catch (\Throwable $e) {
            Log::warning('[FetchTeamInventory] Query failed', [
                'team'  => $this->teamValue,
                'date'  => $this->date,
                'error' => $e->getMessage(),
            ]);
            $result = null;
        }

        Cache::put(
            self::cacheKey($this->teamValue, $this->date),
            $result,
            $this->cacheTtl(),
        );
    }

    /**
     * Build the per-team cache key.
     */
    public static function cacheKey(string $team, string $date): string
    {
        return "fplatform:team-inventory:{$team}:{$date}";
    }

    private function cacheTtl(): int
    {
        return $this->date === now()->toDateString()
            ? self::CACHE_TTL_TODAY
            : self::CACHE_TTL_HISTORICAL;
    }
}
