<?php

namespace App\Containers\AppSection\FplatformData\UI\API\Controllers;

use App\Containers\AppSection\FplatformData\Actions\GetDailyInventoryAction;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\FplatformData\UI\API\Requests\GetDailyInventoryRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class GetDailyInventoryController extends ApiController
{
    /**
     * Cache durations.
     * - Historical data (past dates): won't change → cache 1 hour
     * - Today's data: actively changing → cache 5 minutes
     */
    private const CACHE_TTL_HISTORICAL = 3600;  // 1 hour
    private const CACHE_TTL_TODAY      = 300;    // 5 minutes

    public function __construct(
        private readonly GetDailyInventoryAction $action,
    ) {
    }

    public function __invoke(GetDailyInventoryRequest $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());
        $team = Team::from($request->input('team'));
        $factory = $request->input('factory');

        $isToday = $date === now()->toDateString();
        $cacheKey = "fplatform:inventory:{$team->value}:{$factory}:{$date}";
        $ttl = $isToday ? self::CACHE_TTL_TODAY : self::CACHE_TTL_HISTORICAL;

        $result = Cache::remember($cacheKey, $ttl, fn () => $this->action->run($date, $team));

        if (!$result) {
            return response()->json([
                'message' => 'Không có dữ liệu tồn cho ngày này.',
            ], 404);
        }

        return response()->json([
            'data' => array_merge($result, [
                'team'    => $team->value,
                'factory' => $factory ? strtoupper($factory) : null,
            ]),
        ]);
    }
}
