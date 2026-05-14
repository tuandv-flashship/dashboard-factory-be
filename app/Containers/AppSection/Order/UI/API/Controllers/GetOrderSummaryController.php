<?php

namespace App\Containers\AppSection\Order\UI\API\Controllers;

use App\Containers\AppSection\Order\Actions\GetOrderSummaryAction;
use App\Containers\AppSection\Order\UI\API\Transformers\OrderSummaryTransformer;
use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Ship\Parents\Controllers\ApiController;
use App\Ship\Requests\ShiftFilterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class GetOrderSummaryController extends ApiController
{
    private const CACHE_TTL_TODAY = 300;    // 5 minutes

    public function __construct(
        private readonly GetOrderSummaryAction $action,
    ) {
    }

    public function __invoke(ShiftFilterRequest $request): JsonResponse
    {
        $date = $request->filterDate();
        $shift = $request->filterShift();
        $isToday = $date === null || $date === now()->toDateString();

        $data = $this->action->run($date, $shift);

        // Cache key from centralized ProductionCacheKeys (single source of truth)
        $cacheKey = ProductionCacheKeys::orderSummary($data['date'], $data['shift_number']);
        $ttl = $isToday ? self::CACHE_TTL_TODAY : ProductionCacheKeys::TTL_HISTORICAL;

        $response = Cache::remember($cacheKey, $ttl, function () use ($data) {
            $transformer = new OrderSummaryTransformer();

            return [
                'data' => [
                    'date'         => $data['date'],
                    'shift_number' => $data['shift_number'],
                    'total'        => $data['total'],  // already computed array or null
                    'per_line'     => $data['per_line']->map(fn ($o) => $transformer->transform($o))->values(),
                ],
            ];
        });

        return response()->json($response);
    }
}
