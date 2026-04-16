<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Actions\GetDailyInventoryAction;
use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Get inventory (tổng việc) for ALL teams.
 *
 * Queries run sequentially — with Redis cache (5min/1h TTL),
 * 99%+ of requests are served from cache in <1ms.
 * Cold queries (~5-7 teams) take ~10-15s total but only
 * occur once per cache TTL window.
 *
 * Team counts:
 *   - FLS: 5 DTF teams
 *   - PD:  5 DTF + 2 DTG = 7 teams
 */
final class GetAllTeamsInventoryTask extends ParentTask
{
    /** @var int Cache TTL for historical dates (seconds) */
    private const CACHE_TTL_HISTORICAL = 3600;

    /** @var int Cache TTL for today's date (seconds) */
    private const CACHE_TTL_TODAY = 300;

    /** Teams to include in the inventory response (excludes dtg_print) */
    private const DTF_TEAMS = [
        Team::Print,
        Team::Cut,
        Team::Pick,
        Team::Mockup,
        Team::PackShip,
        Team::OrderInventory,
    ];

    private const DTG_TEAMS = [
        Team::PickDtg,
        Team::DtgPrintSplit,
    ];

    /**
     * @return array{date: string, teams: array}
     */
    public function run(string $date): array
    {
        $cacheKey = "fplatform:all-inventory:{$date}";

        return Cache::remember(
            $cacheKey,
            $this->cacheTtl($date),
            fn () => $this->fetchAllTeams($date),
        );
    }

    /**
     * Execute inventory queries sequentially for each team.
     *
     * Why sequential instead of Concurrency::run()?
     * - process driver spawns child artisan processes (~2-3s bootstrap each)
     * - With Octane in-memory, children DON'T share the warm state
     * - Benchmark showed concurrent (17s) was SLOWER than sequential (15s)
     * - fork driver doesn't work in web requests
     * - Octane::concurrently() requires Swoole (we use FrankenPHP)
     * - With Redis cache, cold calls are rare (once per 5min/1h)
     */
    private function fetchAllTeams(string $date): array
    {
        $factory = FactoryLine::current();
        $action = app(GetDailyInventoryAction::class);

        $teams = array_merge(
            self::DTF_TEAMS,
            $factory === FactoryLine::PD ? self::DTG_TEAMS : [],
        );

        $results = [];
        foreach ($teams as $team) {
            try {
                $results[$team->value] = $action->run($date, $team);
            } catch (\Throwable $e) {
                Log::warning('[FplatformData] Inventory query failed', [
                    'team'  => $team->value,
                    'date'  => $date,
                    'error' => $e->getMessage(),
                ]);
                $results[$team->value] = null;
            }
        }

        return $this->formatResponse($date, $results);
    }

    /**
     * Format results into the API response structure.
     * Always returns full format (tong_viec=0 when no data).
     */
    private function formatResponse(string $date, array $results): array
    {
        $teams = [];
        $factory = FactoryLine::current();
        $defaults = ['estimate_date' => $date, 'tong_viec' => 0];

        // DTF teams: flat structure (one factory per deployment)
        foreach (self::DTF_TEAMS as $team) {
            $result = $results[$team->value] ?? null;
            $teams[$team->value] = array_merge(
                ['label' => $team->label()],
                $result ?: $defaults,
            );
        }

        // DTG teams: only in PD factory
        if ($factory === FactoryLine::PD) {
            foreach (self::DTG_TEAMS as $team) {
                $result = $results[$team->value] ?? null;
                $teams[$team->value] = array_merge(
                    ['label' => $team->label()],
                    $result ?: $defaults,
                );
            }
        }

        return [
            'date'  => $date,
            'teams' => $teams,
        ];
    }

    private function cacheTtl(string $date): int
    {
        return $date === now()->toDateString()
            ? self::CACHE_TTL_TODAY
            : self::CACHE_TTL_HISTORICAL;
    }
}
