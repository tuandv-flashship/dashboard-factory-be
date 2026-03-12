<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Production\Actions\GetLineSummaryAction;
use App\Containers\AppSection\Production\UI\API\Transformers\DepartmentTransformer;
use App\Containers\AppSection\Production\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;
use App\Ship\Requests\ShiftFilterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class GetLineSummaryController extends ApiController
{
    public function __construct(
        private readonly GetLineSummaryAction $action,
    ) {
    }

    public function __invoke(string $line, ShiftFilterRequest $request): JsonResponse
    {
        $date = $request->filterDate();
        $shift = $request->filterShift();
        $isHistorical = $date !== null;

        // Cache historical data for 1 hour (it won't change)
        $cacheKey = $isHistorical ? "line-summary:{$line}:{$date}:{$shift}" : null;

        if ($cacheKey && Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $data = $this->action->run($line, $date, $shift);

        if ($data['shift'] === null) {
            return response()->json(['message' => 'No active shift found'], 404);
        }

        $departments = collect($data['departments'])->map(function ($deptData) {
            $dept = $deptData['department'];
            $records = $deptData['hourly'];

            return [
                'department' => (new DepartmentTransformer())->transform($dept),
                'staff' => $deptData['staff'],
                'efficiency' => $deptData['efficiency'],
                'error_rate' => $deptData['error_rate'],
                'hourly' => $records->map(fn ($r) => [
                    'target' => $r->target,
                    'actual' => $r->actual,
                ])->values(),
            ];
        });

        $pickData = $data['pick']->map(fn ($r) => [
            'target' => $r->target,
            'actual' => $r->actual,
        ])->values();

        $response = [
            'data' => [
                'shift' => (new ShiftTransformer())->transform($data['shift']),
                'line' => [
                    'code' => $data['line']->code,
                    'label' => $data['line']->label,
                    'color' => $data['line']->color,
                ],
                'departments' => $departments->values(),
                'pick' => [
                    'staff' => $data['pick']->first()?->staff ?? 0,
                    'efficiency' => $data['pick']->first()?->efficiency ?? 0,
                    'error_rate' => $data['pick']->first()?->error_rate ?? 0,
                    'total_picked' => $data['pick']->first()?->total_picked ?? 0,
                    'hourly' => $pickData,
                ],
            ],
        ];

        if ($cacheKey) {
            Cache::put($cacheKey, $response, now()->addHour());
        }

        return response()->json($response);
    }
}
