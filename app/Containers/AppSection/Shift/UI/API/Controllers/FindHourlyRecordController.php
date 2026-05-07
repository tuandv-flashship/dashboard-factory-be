<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordTransformer;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\UI\API\Requests\FindHourlyRecordRequest;
use App\Ship\Parents\Controllers\ApiController;
use Apiato\Support\Facades\Response;
use Illuminate\Http\JsonResponse;

/**
 * GET /v1/admin/hourly-records/{id}
 *
 * Returns a single hourly record with issues, department, hourlyMachines
 * and shiftDetail eager-loaded to support the TargetEstimator in the transformer.
 *
 * Also appends shift_detail context (available machines for FE form rendering).
 */
final class FindHourlyRecordController extends ApiController
{
    public function __invoke(FindHourlyRecordRequest $request): JsonResponse
    {
        $record = HourlyRecord::with(['issues', 'department.machines', 'hourlyMachines.machine'])
            ->findOrFail($request->id);

        // Manually load shiftDetail — compound key (shift_id + department_id)
        // doesn't support eager loading via with()
        $shiftDetail = ShiftDetail::where('shift_id', $record->shift_id)
            ->where('department_id', $record->department_id)
            ->with(['machines.machine'])
            ->first();

        if ($shiftDetail) {
            $record->setRelation('shiftDetail', $shiftDetail);
        }

        $response = Response::create($record, HourlyRecordTransformer::class)->ok();

        // Append shift_detail context (available machines for FE form)
        $responseData = $response->getData(true);
        $responseData['data']['shift_detail'] = [
            'headcount'     => $shiftDetail?->headcount,
            'machine_count' => $shiftDetail?->machine_count,
            'kpi_per_hour'  => $shiftDetail?->kpi_per_hour,
            'machines'      => $shiftDetail?->machines->map(fn ($sdm) => [
                'id'           => $sdm->machine?->getHashedKey(),
                'name'         => $sdm->machine?->name,
                'code'         => $sdm->machine?->code,
                'kpi_per_hour' => $sdm->kpi_per_hour,
            ])->values()->toArray() ?? [],
        ];

        // All department machines → FE renders checkboxes for per-slot machine selection
        $responseData['data']['available_machines'] = $record->department?->toAvailableMachines() ?? [];

        return response()->json($responseData, 200);
    }
}
