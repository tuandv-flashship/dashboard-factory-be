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

            // Merge production_workload from all lines (DTF + DTG) by estimate_date
            $mergedWorkload = $this->mergeProductionWorkload($data['per_line']);

            $total = $data['total'];
            if ($total !== null) {
                $total['production_workload'] = $mergedWorkload;
            }

            return [
                'data' => [
                    'date'         => $data['date'],
                    'shift_number' => $data['shift_number'],
                    'total'        => $total,
                    'per_line'     => $data['per_line']->map(fn ($o) => $transformer->transform($o))->values(),
                ],
            ];
        });

        return response()->json($response);
    }

    /**
     * Merge production_workload_json from all per-line OrderSummary rows.
     *
     * Combines DTF + DTG workload by estimate_date → produces the "All" tab data.
     * Each row: { estimate_date, tong_don, da_lam, chua_lam }
     *
     * @param  \Illuminate\Support\Collection<OrderSummary>  $perLine
     * @return array|null
     */
    private function mergeProductionWorkload($perLine): ?array
    {
        $indexed = [];

        foreach ($perLine as $lineRow) {
            $workload = $lineRow->production_workload_json;
            if (empty($workload)) {
                continue;
            }

            foreach ($workload as $item) {
                $date = $item['estimate_date'] ?? null;
                if ($date === null) {
                    continue;
                }

                if (!isset($indexed[$date])) {
                    $indexed[$date] = [
                        'estimate_date' => $date,
                        'tong_don'      => 0,
                        'da_lam'        => 0,
                        'chua_lam'      => 0,
                    ];
                }

                $indexed[$date]['tong_don'] += (int) ($item['tong_don'] ?? 0);
                $indexed[$date]['da_lam']   += (int) ($item['da_lam'] ?? 0);
                $indexed[$date]['chua_lam'] += (int) ($item['chua_lam'] ?? 0);
            }
        }

        if (empty($indexed)) {
            return null;
        }

        // Sort by estimate_date DESC (matching mockup order)
        krsort($indexed);

        return array_values($indexed);
    }
}
