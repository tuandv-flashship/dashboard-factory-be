<?php

namespace App\Containers\AppSection\Machine\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Machine\UI\API\Requests\UpdateMachineRequest;
use App\Containers\AppSection\Machine\UI\API\Transformers\MachineTransformer;
use App\Containers\AppSection\Shift\Tasks\PropagateKpiToShiftDetailsTask;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateMachineController extends ApiController
{
    public function __invoke(UpdateMachineRequest $request): JsonResponse
    {
        $machine = Machine::findOrFail($request->id);

        // Snapshot old KPI before update for change detection
        $validated = $request->validated();
        $oldKpi = $machine->kpi_per_hour;

        $machine->update($validated);

        // Cascade kpi_per_hour to shift_detail_machines + hourly_record_machines
        // Only for per_machine_dtg departments
        if (
            isset($validated['kpi_per_hour'])
            && (int) $oldKpi !== (int) $validated['kpi_per_hour']
            && $machine->department?->productivity_type?->isPerMachineDtg()
        ) {
            app(PropagateKpiToShiftDetailsTask::class)
                ->propagateMachineKpi($machine->id, (int) $validated['kpi_per_hour']);
        }

        return Response::create($machine, MachineTransformer::class)->ok();
    }
}

