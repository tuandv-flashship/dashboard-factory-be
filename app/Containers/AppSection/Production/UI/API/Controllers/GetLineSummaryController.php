<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Production\Actions\GetLineSummaryAction;
use App\Containers\AppSection\Department\UI\API\Transformers\DepartmentTransformer;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordTransformer;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftDetailTransformer;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
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
        $isHistorical = $date !== null && $date < now()->toDateString();

        // Cache historical data for 1 hour (it won't change)
        $cacheKey = $isHistorical ? "line-summary:{$line}:{$date}:{$shift}" : null;

        if ($cacheKey && Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $data = $this->action->run($line, $date, $shift);

        if ($data['shift'] === null) {
            return response()->json(['message' => 'No active shift found'], 404);
        }

        $deptTransformer = new DepartmentTransformer();
        $shiftDetailTransformer = new ShiftDetailTransformer();
        $hourlyTransformer = new HourlyRecordTransformer();

        $departments = collect($data['departments'])->map(function ($deptData) use ($deptTransformer, $shiftDetailTransformer, $hourlyTransformer) {
            return [
                'department'   => $deptTransformer->transform($deptData['department']),
                'shift_detail' => $deptData['shift_detail']
                    ? $shiftDetailTransformer->transform($deptData['shift_detail'])
                    : null,
                'hourly'       => $deptData['hourly']->map(
                    fn ($r) => $hourlyTransformer->transform($r)
                )->values(),
            ];
        });

        $response = [
            'data' => [
                'shift' => (new ShiftTransformer())->transform($data['shift']),
                'line' => [
                    'code'     => $data['line']->code,
                    'label'    => $data['line']->label,
                    'color'    => $data['line']->color,
                    'subtitle' => $data['line']->subtitle,
                ],
                'departments' => $departments->values(),
            ],
        ];

        if ($cacheKey) {
            Cache::put($cacheKey, $response, now()->addHour());
        }

        return response()->json($response);
    }
}
