<?php

namespace App\Containers\AppSection\Order\UI\API\Controllers;

use App\Containers\AppSection\Order\Actions\GetOrderSummaryHistoryAction;
use App\Containers\AppSection\Order\UI\API\Requests\GetOrderSummaryHistoryRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class GetOrderSummaryHistoryController extends ApiController
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly GetOrderSummaryHistoryAction $action,
    ) {
    }

    public function __invoke(GetOrderSummaryHistoryRequest $request): JsonResponse
    {
        $days = $request->filterDays();
        $line = $request->filterLine();

        $cacheKey = "order-summary-history:{$days}:" . ($line ?? 'all');

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($days, $line) {
            $history = $this->action->run($days, $line);

            // Compute summary totals across all dates
            $summary = [
                'total'     => $history->sum('total'),
                'completed' => $history->sum('completed'),
                'remaining' => $history->sum('remaining'),
            ];

            return [
                'data' => [
                    'line'    => $line,
                    'days'    => $days,
                    'summary' => $summary,
                    'history' => $history,
                ],
            ];
        });

        return response()->json($data);
    }
}
