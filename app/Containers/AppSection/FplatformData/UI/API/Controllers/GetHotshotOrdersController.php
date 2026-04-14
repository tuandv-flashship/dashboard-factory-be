<?php

namespace App\Containers\AppSection\FplatformData\UI\API\Controllers;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Tasks\GetHotshotOrderInventoryTask;
use App\Containers\AppSection\FplatformData\UI\API\Requests\GetHotshotOrdersRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Get hotshot order inventory (tồn đầu/cuối đơn hotshot).
 *
 * Private — requires auth (shifts.index permission or admin role).
 */
final class GetHotshotOrdersController extends ApiController
{
    private const CACHE_TTL_HISTORICAL = 3600;
    private const CACHE_TTL_TODAY      = 300;

    public function __construct(
        private readonly GetHotshotOrderInventoryTask $task,
    ) {
    }

    public function __invoke(GetHotshotOrdersRequest $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());
        $factory = FactoryLine::current();

        $isToday = $date === now()->toDateString();
        $cacheKey = "fplatform:hotshot:{$factory->value}:{$date}";

        $result = Cache::remember(
            $cacheKey,
            $isToday ? self::CACHE_TTL_TODAY : self::CACHE_TTL_HISTORICAL,
            fn () => $this->task->run($date, $factory),
        );

        if (!$result) {
            return response()->json([
                'message' => 'Không có dữ liệu hotshot cho ngày này.',
            ], 404);
        }

        return response()->json([
            'data' => array_merge($result, [
                'factory' => $factory->value,
            ]),
        ]);
    }
}
