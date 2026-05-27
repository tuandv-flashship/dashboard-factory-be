<?php

namespace App\Containers\AppSection\Order\UI\API\Controllers;

use App\Containers\AppSection\Order\Actions\GetOrderSummaryAction;
use App\Containers\AppSection\Order\UI\API\Requests\GetOrderSummaryRequest;
use App\Containers\AppSection\Order\UI\API\Transformers\OrderSummaryTransformer;
use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * GET /v1/orders/summary — Order summary (total + per-line).
 *
 * Performance: resolves shift first (1 query), then wraps ALL DB queries
 * + transformation inside Cache::remember(). On cache hit → 0 heavy queries.
 */
final class GetOrderSummaryController extends ApiController
{
    private const CACHE_TTL_TODAY = 300;    // 5 minutes

    public function __construct(
        private readonly GetOrderSummaryAction $action,
    ) {
    }

    public function __invoke(GetOrderSummaryRequest $request): JsonResponse
    {
        $date = $request->filterDate();
        $shiftNumber = $request->filterShift();
        $isToday = $date === null || $date === now()->toDateString();

        // Resolve shift number (1 lightweight query)
        $resolvedDate = $date ?? now()->toDateString();
        if ($shiftNumber === null) {
            $shift = Shift::query()
                ->where('date', $resolvedDate)
                ->latest('shift_number')
                ->first();
            $resolvedShiftNumber = $shift?->shift_number ?? 1;
        } else {
            $resolvedShiftNumber = $shiftNumber;
        }

        $cacheKey = ProductionCacheKeys::orderSummary($resolvedDate, $resolvedShiftNumber);
        $ttl = $isToday ? self::CACHE_TTL_TODAY : ProductionCacheKeys::TTL_HISTORICAL;

        // Wrap ALL heavy queries + transformation inside cache
        $response = Cache::remember($cacheKey, $ttl, function () use ($resolvedDate, $resolvedShiftNumber) {
            $data = $this->action->run($resolvedDate, $resolvedShiftNumber);

            $transformer = new OrderSummaryTransformer();

            return [
                'data' => [
                    'date'         => $data['date'],
                    'shift_number' => $data['shift_number'],
                    'total'        => $data['total'],
                    'per_line'     => $data['per_line']->map(fn ($o) => $transformer->transform($o))->values(),
                ],
            ];
        });

        return response()->json($response);
    }
}
