<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Actions\GetDailyInventoryAction;
use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\FplatformData\Jobs\FetchTeamInventoryJob;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Get inventory (tổng việc) for ALL teams.
 *
 * Two modes:
 *   1. run()                   — Serve API requests. Reads from composite cache,
 *                                 falls back to per-team cache, then sequential fetch.
 *   2. dispatchParallelFetch() — Dispatches FetchTeamInventoryJob × N via Bus::batch().
 *                                 Each job caches its result individually.
 *   3. assembleFromCache()     — Builds allInventory from per-team cache keys.
 *                                 Called after parallel batch completes.
 *
 * Team counts:
 *   - FLS: 6 DTF teams + 5 hotshot = 11 teams
 *   - PD:  6 DTF + 2 DTG + 5 hotshot = 13 teams
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

    private const HOTSHOT_TEAMS = [
        Team::HotshotPrint,
        Team::HotshotPick,
        Team::HotshotCut,
        Team::HotshotMockup,
        Team::HotshotPackShip,
    ];

    // ── Mode 1: API / Synchronous ────────────────────────

    /**
     * Serve API requests — composite cache → per-team cache → sequential fallback.
     *
     * @return array{date: string, teams: array}
     */
    public function run(string $date): array
    {
        $cacheKey = "fplatform:all-inventory:{$date}";

        return Cache::remember(
            $cacheKey,
            $this->cacheTtl($date),
            function () use ($date) {
                // Try assemble from per-team cache first (parallel batch already ran)
                $assembled = $this->assembleFromCache($date);
                if ($assembled) {
                    return $assembled;
                }

                // Fallback: sequential fetch (API endpoint, manual resync)
                return $this->fetchAllTeamsSequential($date);
            },
        );
    }

    // ── Mode 2: Parallel Dispatch ────────────────────────

    /**
     * Dispatch FetchTeamInventoryJob for each team via Bus::batch().
     * Returns PendingBatch — caller attaches then() callback for Stage 2.
     */
    public function dispatchParallelFetch(string $date): PendingBatch
    {
        $teams = $this->resolveTeams();

        $jobs = array_map(
            fn (Team $team) => (new FetchTeamInventoryJob($team->value, $date))
                ->onQueue('sync'),
            $teams,
        );

        return Bus::batch($jobs)
            ->name("fetch-inventory:{$date}")
            ->onQueue('sync')
            ->allowFailures();
    }

    // ── Mode 3: Assemble from Per-Team Cache ─────────────

    /**
     * Build allInventory from individual per-team cache keys.
     * Returns null if any required team is missing from cache.
     */
    public function assembleFromCache(string $date): ?array
    {
        $teams = $this->resolveTeams();
        $results = [];

        foreach ($teams as $team) {
            $cacheKey = FetchTeamInventoryJob::cacheKey($team->value, $date);

            if (!Cache::has($cacheKey)) {
                return null; // Not all teams are cached yet
            }

            $results[$team->value] = Cache::get($cacheKey);
        }

        $allInventory = $this->formatResponse($date, $results);

        // Cache composite for subsequent reads (API, SyncOrderInventoryTask)
        Cache::put(
            "fplatform:all-inventory:{$date}",
            $allInventory,
            $this->cacheTtl($date),
        );

        return $allInventory;
    }

    // ── Helpers ──────────────────────────────────────────

    /**
     * Resolve teams to fetch based on current factory.
     *
     * @return Team[]
     */
    public function resolveTeams(): array
    {
        $factory = FactoryLine::current();

        return array_merge(
            self::DTF_TEAMS,
            $factory === FactoryLine::PD ? self::DTG_TEAMS : [],
            self::HOTSHOT_TEAMS,
        );
    }

    /**
     * Sequential fetch — fallback for API and manual resync.
     */
    private function fetchAllTeamsSequential(string $date): array
    {
        $action = app(GetDailyInventoryAction::class);
        $teams = $this->resolveTeams();
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
        $hotshotDefaults = ['estimate_date' => $date, 'tong_viec' => 0, 'da_lam' => 0];

        // DTF teams
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

        // Hotshot teams: all factories
        foreach (self::HOTSHOT_TEAMS as $team) {
            $result = $results[$team->value] ?? null;
            $teams[$team->value] = array_merge(
                ['label' => $team->label()],
                $result ?: $hotshotDefaults,
            );
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
