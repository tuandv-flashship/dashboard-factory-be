<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Production\Actions\GetDeptDetailAction;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyIssueTransformer;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordTransformer;
use App\Containers\AppSection\Production\UI\API\Transformers\ShiftTransformer;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftDetailTransformer;
use App\Ship\Parents\Controllers\ApiController;
use App\Ship\Requests\ShiftFilterRequest;
use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Production\Support\TargetEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class GetDeptDetailController extends ApiController
{
    public function __construct(
        private readonly GetDeptDetailAction $action,
    ) {
    }

    public function __invoke(string $line, string $dept, ShiftFilterRequest $request): JsonResponse
    {
        $date  = $request->filterDate();
        $shift = $request->filterShift();

        $cacheKey = ProductionCacheKeys::isHistorical($date)
            ? ProductionCacheKeys::deptDetail($line, $dept, $date, $shift)
            : null;

        if ($cacheKey && Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $data = $this->action->run($line, $dept, $date, $shift);

        if ($data['shift'] === null) {
            return response()->json(['message' => 'No active shift found'], 404);
        }

        $hourlyTransformer      = new HourlyRecordTransformer();
        $issueTransformer       = new HourlyIssueTransformer();
        $shiftDetailTransformer = new ShiftDetailTransformer();

        $recordsData = $data['records']->map(
            fn ($r) => array_merge(
                $hourlyTransformer->transform($r),
                ['issues' => $r->issues->map(
                    fn ($i) => $issueTransformer->transform($i)
                )->values()],
            )
        );

        $records           = $data['records'];
        $shiftDetail       = $data['shift_detail'];
        $completedRecords  = $records->whereNotNull('actual');
        $dayStartInventory = $shiftDetail?->day_start_inventory ?? 0;

        // Compute effective target for each record (via centralized TargetEstimator)
        $isPerMachine = $data['department']?->productivity_type === ProductivityType::PerMachine;
        $kpiPerHour   = $isPerMachine
            ? ($shiftDetail?->kpi_per_hour ?? 0)
            : ($data['department']?->kpi_per_hour ?? 0);
        $defaultHeadcount = $shiftDetail?->headcount ?? 0;

        $effectiveTargets = $records->map(fn ($r) => TargetEstimator::effective(
            $r->target,
            $kpiPerHour,
            $r->kpi_percent ?? 100,
            $isPerMachine,
            $r->staff_required ?? $defaultHeadcount,
        ));

        $totalTarget    = $effectiveTargets->sum();
        $totalCompleted = $completedRecords->sum('actual');
        $remaining      = max(0, $dayStartInventory - $totalCompleted);

        // Target còn lại = (target block hiện tại - actual block hiện tại) + Σ(target block pending)
        $targetRemaining = 0;
        foreach ($records as $i => $r) {
            $effectiveTarget = $effectiveTargets[$i];
            if ($r->status === 'active') {
                $targetRemaining += max(0, $effectiveTarget - ($r->actual ?? 0));
            } elseif ($r->status === 'pending') {
                $targetRemaining += $effectiveTarget;
            }
        }

        // Tồn cuối = Tổng việc - Đã làm - Target còn lại (min 0)
        $endingInventory = max(0, $dayStartInventory - $totalCompleted - $targetRemaining);

        $response = [
            'data' => [
                'shift'        => (new ShiftTransformer())->transform($data['shift']),
                'type'         => $data['type'],
                'line'         => [
                    'code'  => $data['line']->code,
                    'label' => $data['line']->label,
                ],
                'department'   => isset($data['department'])
                    ? $this->transformDepartment($data['department'], $data['shift_detail'])
                    : null,
                'shift_detail' => $shiftDetailTransformer->transform($data['shift_detail']),
                'hours'        => $recordsData->values(),
                'summary'      => [
                    'total_target'        => $totalTarget,
                    'total_completed'     => $totalCompleted,
                    'completed'           => $totalCompleted,
                    'target_remaining'    => $targetRemaining,
                    'ending_inventory'    => $endingInventory,
                    'remaining'           => $remaining,
                    'day_start_inventory' => $dayStartInventory,
                    'hotshot_total'       => $shiftDetail?->hotshot_total ?? 0,
                    'hotshot_completed'   => $shiftDetail?->hotshot_completed ?? 0,
                    'efficiency'          => $this->calculateEfficiency($completedRecords),
                    'error_rate'          => 0,
                ],
            ],
        ];

        if ($cacheKey) {
            Cache::put($cacheKey, $response, ProductionCacheKeys::TTL_HISTORICAL);
        }

        return response()->json($response);
    }

    private function transformDepartment(mixed $dept, mixed $shiftDetail): array
    {
        $isPerMachine = $dept->productivity_type === ProductivityType::PerMachine;
        $kpiPerHour   = $isPerMachine
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
    }

    private function calculateEfficiency(Collection $completedRecords): float|int
    {
        $withEfficiency = $completedRecords->where('efficiency', '>', 0);

        return $withEfficiency->isNotEmpty()
            ? round($withEfficiency->avg('efficiency'), 2)
            : 0;
    }
}
