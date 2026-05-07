<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Production\Actions\GetDeptDetailAction;
use App\Containers\AppSection\Production\Support\DepartmentSummary;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyIssueTransformer;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordTransformer;
use App\Containers\AppSection\Production\UI\API\Transformers\ShiftTransformer;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftDetailTransformer;
use App\Ship\Parents\Controllers\ApiController;
use App\Ship\Requests\ShiftFilterRequest;
use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
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

        $hourlyTransformer      = (new HourlyRecordTransformer())->setShiftDate($data['shift']->date);
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

        $records     = $data['records'];
        $shiftDetail = $data['shift_detail'];
        $dept        = $data['department'];

        $summary = DepartmentSummary::build($records, $dept, $shiftDetail, $data['shift']->date);

        $response = [
            'data' => [
                'shift'        => (new ShiftTransformer())->transform($data['shift']),
                'type'         => $data['type'],
                'line'         => [
                    'code'  => $data['line']->code,
                    'label' => $data['line']->label,
                ],
                'department'   => $dept
                    ? $this->transformDepartment($dept, $shiftDetail)
                    : null,
                'shift_detail' => $shiftDetailTransformer->transform($shiftDetail),
                'hours'        => $recordsData->values(),
                'summary'      => $summary,
            ],
        ];

        if ($cacheKey) {
            Cache::put($cacheKey, $response, ProductionCacheKeys::TTL_HISTORICAL);
        }

        return response()->json($response);
    }

    private function transformDepartment(mixed $dept, mixed $shiftDetail): array
    {
        $isPerMachineDtg = $dept->productivity_type?->isPerMachineDtg() ?? false;
        $kpiPerHour   = $isPerMachineDtg
            ? ($shiftDetail?->kpi_per_hour ?? 0)
            : $dept->kpi_per_hour;

        $data = [
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

        // DTG: include all department machines for FE checkbox rendering
        if ($dept->relationLoaded('machines')) {
            $data['available_machines'] = $dept->machines->map(fn ($m) => [
                'id'           => $m->getHashedKey(),
                'code'         => $m->code,
                'name'         => $m->name,
                'kpi_per_hour' => $m->kpi_per_hour,
                'status'       => $m->status?->value,
                'is_active'    => $m->is_active,
            ])->values()->all();
        } else {
            $data['available_machines'] = [];
        }

        return $data;
    }

}
