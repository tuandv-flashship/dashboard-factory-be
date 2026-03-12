<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Production\Actions\GetDeptDetailAction;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyIssueTransformer;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordTransformer;
use App\Containers\AppSection\Production\UI\API\Transformers\PickHourlyRecordTransformer;
use App\Containers\AppSection\Production\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;
use App\Ship\Requests\ShiftFilterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class GetDeptDetailController extends ApiController
{
    public function __construct(
        private readonly GetDeptDetailAction $action,
    ) {
    }

    public function __invoke(string $line, string $dept, ShiftFilterRequest $request): JsonResponse
    {
        $date = $request->filterDate();
        $shift = $request->filterShift();
        $isHistorical = $date !== null;

        $cacheKey = $isHistorical ? "dept-detail:{$line}:{$dept}:{$date}:{$shift}" : null;

        if ($cacheKey && Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $data = $this->action->run($line, $dept, $date, $shift);

        if ($data['shift'] === null) {
            return response()->json(['message' => 'No active shift found'], 404);
        }

        $hourlyTransformer = new HourlyRecordTransformer();
        $issueTransformer = new HourlyIssueTransformer();
        $pickTransformer = new PickHourlyRecordTransformer();

        if ($data['type'] === 'pick') {
            $recordsData = $data['records']->map(
                fn ($r) => $pickTransformer->transform($r)
            );
        } else {
            $recordsData = $data['records']->map(
                fn ($r) => array_merge(
                    $hourlyTransformer->transform($r),
                    ['issues' => $r->issues->map(
                        fn ($i) => $issueTransformer->transform($i)
                    )->values()],
                )
            );
        }

        // Calculate summary
        $records = $data['records'];
        $completedRecords = $records->whereNotNull('actual');
        $totalCompleted = $completedRecords->sum('actual');
        $totalTarget = $records->sum('target');

        $response = [
            'data' => [
                'shift' => (new ShiftTransformer())->transform($data['shift']),
                'type' => $data['type'],
                'line' => [
                    'code' => $data['line']->code,
                    'label' => $data['line']->label,
                ],
                'department' => isset($data['department']) ? [
                    'code' => $data['department']->code,
                    'label' => $data['department']->label,
                    'label_en' => $data['department']->label_en,
                    'unit' => $data['department']->unit,
                ] : null,
                'hours' => $recordsData->values(),
                'summary' => [
                    'total_target' => $totalTarget,
                    'completed' => $totalCompleted,
                    'remaining' => max(0, $totalTarget - $totalCompleted),
                    'staff' => $records->first()?->staff ?? 0,
                    'efficiency' => $records->first()?->efficiency ?? 0,
                    'error_rate' => $records->first()?->error_rate ?? 0,
                ],
            ],
        ];

        if ($cacheKey) {
            Cache::put($cacheKey, $response, now()->addHour());
        }

        return response()->json($response);
    }
}

