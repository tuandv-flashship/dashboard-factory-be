<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Actions\GetDailyInventoryAction;
use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Log;

/**
 * Get inventory (tồn đầu/cuối ngày) for ALL teams in parallel.
 *
 * Uses Laravel Concurrency (fork driver via pcntl) to execute
 * 12 queries simultaneously:
 *   - 5 DTF teams × 2 factories (FLS, PD) = 10 queries
 *   - 2 DTG teams (dtg_pick, dtg_print_split) = 2 queries
 *
 * Results are cached:
 *   - Today's data: 5 minutes
 *   - Historical data: 1 hour
 */
final class GetAllTeamsInventoryTask extends ParentTask
{
    /** @var int Cache TTL for historical dates (seconds) */
    private const CACHE_TTL_HISTORICAL = 3600;

    /** @var int Cache TTL for today's date (seconds) */
    private const CACHE_TTL_TODAY = 300;

    /** Teams to include in the inventory response (excludes dtg_print) */
    private const DTF_TEAMS = [
        Team::In,
        Team::Cat,
        Team::Pick,
        Team::Mockup,
        Team::PackShip,
    ];

    private const DTG_TEAMS = [
        Team::DtgPick,
        Team::DtgPrintSplit,
    ];

    public function __construct(
        private readonly GetDailyInventoryAction $action,
    ) {
    }

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
     * Execute all inventory queries in parallel via Concurrency::run().
     */
    private function fetchAllTeams(string $date): array
    {
        // Build closures — each closure = 1 query
        $jobs = [];
        $factory = FactoryLine::current();

        foreach (self::DTF_TEAMS as $team) {
            $jobs[$team->value] = fn () => $this->safeRun($date, $team);
        }

        // DTG teams only exist in PD factory
        if ($factory === FactoryLine::PD) {
            foreach (self::DTG_TEAMS as $team) {
                $jobs[$team->value] = fn () => $this->safeRun($date, $team);
            }
        }

        // Run all queries concurrently (fork driver via pcntl)
        $results = Concurrency::run($jobs);

        // Format into structured response
        return $this->formatResponse($date, $results);
    }

    /**
     * Safely execute a single team inventory query.
     * Returns null on failure instead of throwing.
     */
    private function safeRun(string $date, Team $team): ?array
    {
        try {
            return $this->action->run($date, $team);
        } catch (\Throwable $e) {
            Log::warning('[FplatformData] Inventory query failed', [
                'team'    => $team->value,
                'factory' => FactoryLine::current()->value,
                'date'    => $date,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Format concurrent results into the API response structure.
     */
    private function formatResponse(string $date, array $results): array
    {
        $teams = [];
        $factory = FactoryLine::current();

        // DTF teams: flat structure (one factory per deployment)
        foreach (self::DTF_TEAMS as $team) {
            $result = $results[$team->value] ?? null;

            $teams[$team->value] = $result
                ? array_merge(['label' => $team->label()], $result)
                : ['label' => $team->label()];
        }

        // DTG teams: only in PD factory
        if ($factory === FactoryLine::PD) {
            foreach (self::DTG_TEAMS as $team) {
                $result = $results[$team->value] ?? null;

                $teams[$team->value] = $result
                    ? array_merge(['label' => $team->label()], $result)
                    : ['label' => $team->label()];
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
