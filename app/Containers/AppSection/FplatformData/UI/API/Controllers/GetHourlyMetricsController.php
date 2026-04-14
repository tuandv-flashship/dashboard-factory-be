<?php

namespace App\Containers\AppSection\FplatformData\UI\API\Controllers;

use App\Containers\AppSection\FplatformData\Actions\GetHourlyMetricsAction;
use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\FplatformData\UI\API\Requests\GetHourlyMetricsRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Get hourly metrics (productivity, staff count, machine, per-staff) from FPlatform.
 *
 * Single endpoint covering 14 query variants via team + metric params.
 * Private — requires auth (shifts.index permission or admin role).
 *
 * Cache: shift-based queries cache for 5 minutes (data updates in real-time).
 */
final class GetHourlyMetricsController extends ApiController
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly GetHourlyMetricsAction $action,
    ) {
    }

    public function __invoke(GetHourlyMetricsRequest $request): JsonResponse
    {
        $team = Team::from($request->input('team'));
        $metric = HourlyMetricType::from($request->input('metric'));
        $startShift = $request->input('start_shift');
        $endShift = $request->input('end_shift');

        $cacheKey = "fplatform:hourly:{$team->value}:{$metric->value}:{$startShift}:{$endShift}";

        $result = Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->action->run($team, $metric, $startShift, $endShift),
        );

        return response()->json(['data' => $result]);
    }
}
