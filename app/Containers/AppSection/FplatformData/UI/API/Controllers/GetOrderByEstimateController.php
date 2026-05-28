<?php

namespace App\Containers\AppSection\FplatformData\UI\API\Controllers;

use App\Containers\AppSection\FplatformData\Actions\GetOrderByEstimateAction;
use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\UI\API\Requests\GetOrderByEstimateRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Get order breakdown by estimate date (tổng đơn theo ngày estimate).
 *
 * Private — requires auth (shifts.index permission or admin role).
 */
final class GetOrderByEstimateController extends ApiController
{
    private const CACHE_TTL_HISTORICAL = 3600;  // 1 hour
    private const CACHE_TTL_TODAY      = 300;    // 5 minutes

    public function __construct(
        private readonly GetOrderByEstimateAction $action,
    ) {
    }

    public function __invoke(GetOrderByEstimateRequest $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());
        $factory = FactoryLine::current();

        $isToday = $date === now()->toDateString();
        $cacheKey = "fplatform:order-by-estimate:{$factory->value}:{$date}";

        $result = Cache::remember(
            $cacheKey,
            $isToday ? self::CACHE_TTL_TODAY : self::CACHE_TTL_HISTORICAL,
            fn () => $this->action->run($date),
        );

        return response()->json([
            'data'    => $result,
            'factory' => $factory->value,
            'date'    => $date,
        ]);
    }
}
