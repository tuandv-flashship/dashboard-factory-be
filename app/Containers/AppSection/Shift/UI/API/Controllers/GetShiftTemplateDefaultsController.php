<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Containers\AppSection\Shift\UI\API\Requests\GetShiftTemplateDefaultsRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTemplateDetailTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Serializer\DataArraySerializer;

/**
 * Returns default shift template details for every department.
 *
 * FE uses this to pre-populate the "Thêm mới ca chuẩn" form
 * with the same detail shape as Find Shift Template.
 */
final class GetShiftTemplateDefaultsController extends ApiController
{
    public function __invoke(GetShiftTemplateDefaultsRequest $request): JsonResponse
    {
        $defaults = config('appSection-shift.defaults');

        // Resolve departments keyed by "lineCode-deptCode" (with machines for per_machine)
        $departments = Department::with(['productionLine', 'machines'])->get()
            ->keyBy(fn ($d) => "{$d->productionLine->code}-{$d->code}");

        $details = collect();

        foreach (['ca1' => 1, 'ca2' => 2] as $caKey => $shiftNumber) {
            $caDefaults = $defaults[$caKey] ?? [];
            $fallback   = $caDefaults['_default'] ?? null;

            foreach ($departments as $deptKey => $dept) {
                // Use department-specific values if defined, else fall back to _default
                $values = $caDefaults[$deptKey] ?? $fallback;

                if (! $values) {
                    continue;
                }

                [$start, $hours, $prep, $b1Start, $b1Min, $mealStart, $mealMin, $b2Start, $b2Min, $b3Start, $b3Min] = $values;

                // Create unsaved model instance → same transformer output as Find
                $detail = new ShiftTemplateDetail([
                    'department_id'      => $dept->id,
                    'shift_number'       => $shiftNumber,
                    'headcount'          => 0,
                    'start_time'         => $start,
                    'work_hours'         => $hours,
                    'prep_minutes'       => $prep,
                    'break1_start'       => $b1Start,
                    'break1_minutes'     => $b1Min,
                    'meal_break_start'   => $mealStart,
                    'meal_break_minutes' => $mealMin,
                    'break2_start'       => $b2Start,
                    'break2_minutes'     => $b2Min,
                    'break3_start'       => $b3Start,
                    'break3_minutes'     => $b3Min,
                ]);

                // Manually set the department relation (avoid lazy load violation)
                $detail->setRelation('department', $dept);

                $details->push($detail);
            }
        }

        // Sort: production_line → shift_number → department
        $sorted = $details->sortBy([
            fn ($a, $b) => ($a->department?->productionLine?->sort_order ?? 0) <=> ($b->department?->productionLine?->sort_order ?? 0),
            fn ($a, $b) => $a->shift_number <=> $b->shift_number,
            fn ($a, $b) => ($a->department?->sort_order ?? 0) <=> ($b->department?->sort_order ?? 0),
        ])->values();

        // Transform using the same transformer as Find Shift Template
        $resource = new Collection($sorted, new ShiftTemplateDetailTransformer(), 'details');
        $manager  = new Manager();
        $manager->setSerializer(new DataArraySerializer());

        $response = $manager->createData($resource)->toArray();
        $response['meta'] = [
            'supervisors' => config('appSection-shift.supervisors', []),
        ];

        return new JsonResponse($response);
    }
}
