<?php

namespace App\Containers\AppSection\Order\UI\API\Controllers;

use App\Containers\AppSection\Order\Actions\GetOrderSummaryAction;
use App\Containers\AppSection\Order\UI\API\Transformers\OrderSummaryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use App\Ship\Requests\ShiftFilterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class GetOrderSummaryController extends ApiController
{
    public function __construct(
        private readonly GetOrderSummaryAction $action,
    ) {
    }

    public function __invoke(ShiftFilterRequest $request): JsonResponse
    {
        $date = $request->filterDate();
        $shift = $request->filterShift();
        $isHistorical = $date !== null;

        $cacheKey = $isHistorical ? "order-summary:{$date}:{$shift}" : null;

        if ($cacheKey && Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $data = $this->action->run($date, $shift);
        $transformer = new OrderSummaryTransformer();

        $response = [
            'data' => [
                'total' => $data['total'] ? $transformer->transform($data['total']) : null,
                'per_line' => $data['per_line']->map(fn ($o) => $transformer->transform($o))->values(),
            ],
        ];

        if ($cacheKey) {
            Cache::put($cacheKey, $response, now()->addHour());
        }

        return response()->json($response);
    }
}
