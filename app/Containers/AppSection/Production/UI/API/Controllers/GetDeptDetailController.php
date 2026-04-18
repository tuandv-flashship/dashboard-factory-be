<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Production\Actions\GetDeptDetailAction;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyIssueTransformer;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordTransformer;
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

        $recordsData = $data['records']->map(
            fn ($r) => array_merge(
                $hourlyTransformer->transform($r),
                ['issues' => $r->issues->map(
                    fn ($i) => $issueTransformer->transform($i)
                )->values()],
            )
        );

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
                'department' => isset($data['department']) ? (static function ($dept, $shiftDetail) {
                    $isPerMachine = $dept->productivity_type === ProductivityType::PerMachine;
                    $kpiPerHour = $isPerMachine
                        ? ($shiftDetail?->kpi_per_hour ?? 0)
                        : $dept->kpi_per_hour;

                    return [
                        'id'               => $dept->getHashedKey(),
                        'code'             => $dept->code,
                        'label'            => $dept->label,
                        'label_en'         => $dept->label_en,
                        'description'      => $dept->description,
                        'icon'             => $dept->icon,
                        'unit'             => $dept->unit,
                        'kpi_per_hour'     => $kpiPerHour,
                        'factory'          => $dept->factory,
                        'sort_order'       => $dept->sort_order,
                        'is_active'        => $dept->is_active,
                        'productivity_type'=> $dept->productivity_type,
                    ];
                })($data['department'], $data['shift_detail']) : null,
                'hours' => $recordsData->values(),
                'summary' => [
                    'total_target' => $totalTarget,
                    'completed' => $totalCompleted,
                    'remaining' => max(0, $totalTarget - $totalCompleted),
                    'day_start_inventory' => $data['shift_detail']?->day_start_inventory ?? 0,
                    'hotshot_total' => $data['shift_detail']?->hotshot_total ?? 0,
                    'hotshot_completed' => $data['shift_detail']?->hotshot_completed ?? 0,
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

