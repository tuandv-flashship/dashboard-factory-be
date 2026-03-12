<?php

namespace App\Containers\AppSection\Quality\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Quality\Actions\GetQualityDataAction;
use App\Containers\AppSection\Quality\UI\API\Transformers\QualityRecordTransformer;
use App\Ship\Parents\Controllers\ApiController;
use App\Ship\Requests\ShiftFilterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class GetQualityDataController extends ApiController
{
    public function __construct(
        private readonly GetQualityDataAction $action,
    ) {
    }

    public function __invoke(ShiftFilterRequest $request): JsonResponse
    {
        $date = $request->filterDate();
        $shift = $request->filterShift();
        $isHistorical = $date !== null;

        $cacheKey = $isHistorical ? "quality:{$date}:{$shift}" : null;

        if ($cacheKey && Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $record = $this->action->run($date, $shift);

        if (!$record) {
            return response()->json(['message' => 'No quality data for current shift'], 404);
        }

        $response = Response::create($record, QualityRecordTransformer::class)->ok();

        if ($cacheKey) {
            Cache::put($cacheKey, $response->getData(true), now()->addHour());
        }

        return $response;
    }
}
