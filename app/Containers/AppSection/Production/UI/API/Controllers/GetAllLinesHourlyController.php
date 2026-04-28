<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use App\Containers\AppSection\Department\UI\API\Transformers\DepartmentTransformer;
use App\Containers\AppSection\Production\Support\DepartmentSummary;
use App\Containers\AppSection\Production\Tasks\GetAllLinesHourlyTask;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordTransformer;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftDetailTransformer;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;
use App\Ship\Requests\ShiftFilterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * GET /v1/production/hourly — All lines → departments → hourly records + summary.
 *
 * Supports ?date=&shift= for historical queries.
 * Caching: today = 2 min, historical = 1 hour.
 */
final class GetAllLinesHourlyController extends ApiController
{
    private const CACHE_TTL_TODAY      = 120;   // 2 minutes
    private const CACHE_TTL_HISTORICAL = 3600;  // 1 hour

    public function __invoke(ShiftFilterRequest $request): JsonResponse
    {
        $date = $request->filterDate();
        $shift = $request->filterShift();
        $isToday = $date === null || $date === now()->toDateString();

        $data = app(GetAllLinesHourlyTask::class)->run($date, $shift);

        if ($data['shift'] === null) {
            return response()->json(['message' => 'No active shift found'], 404);
        }

        $resolvedDate = $data['shift']->date->toDateString();
        $resolvedShift = $data['shift']->shift_number;

        $cacheKey = "all-lines-hourly:{$resolvedDate}:{$resolvedShift}";
        $ttl = $isToday ? self::CACHE_TTL_TODAY : self::CACHE_TTL_HISTORICAL;

        $response = Cache::remember($cacheKey, $ttl, function () use ($data) {
            $deptTransformer = new DepartmentTransformer();
            $shiftDetailTransformer = new ShiftDetailTransformer();
            $hourlyTransformer = new HourlyRecordTransformer();

            $lines = collect($data['lines'])->map(function ($lineData) use ($deptTransformer, $shiftDetailTransformer, $hourlyTransformer) {
                $line = $lineData['line'];

                $departments = collect($lineData['departments'])->map(function ($deptData) use ($deptTransformer, $shiftDetailTransformer, $hourlyTransformer) {
                    $dept        = $deptData['department'];
                    $shiftDetail = $deptData['shift_detail'];
                    $hourly      = $deptData['hourly'];

                    return [
                        'department'   => $deptTransformer->transform($dept),
                        'shift_detail' => $shiftDetail ? $shiftDetailTransformer->transform($shiftDetail) : null,
                        'hourly'       => $hourly->map(fn ($r) => $hourlyTransformer->transform($r))->values(),
                        'summary'      => DepartmentSummary::build($hourly, $dept, $shiftDetail),
                    ];
                })->values();

                return [
                    'code'        => $line->code,
                    'label'       => $line->label,
                    'color'       => $line->color,
                    'subtitle'    => $line->subtitle,
                    'departments' => $departments,
                ];
            })->values();

            return [
                'data' => [
                    'shift' => (new ShiftTransformer())->transform($data['shift']),
                    'lines' => $lines,
                ],
            ];
        });

        return response()->json($response);
    }
}
