<?php

namespace App\Containers\AppSection\Department\Actions;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Department\Tasks\UpdateDepartmentTask;
use App\Containers\AppSection\Department\UI\API\Requests\UpdateDepartmentRequest;
use App\Containers\AppSection\Shift\Tasks\PropagateKpiToShiftDetailsTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class UpdateDepartmentAction extends ParentAction
{
    public function run(UpdateDepartmentRequest $request): Department
    {
        $data = array_filter([
            'production_line_id'        => $request->production_line_id,
            'code'                      => $request->code,
            'label'                     => $request->label,
            'label_en'                  => $request->label_en,
            'description'               => $request->description,
            'icon'                      => $request->icon,
            'unit'                      => $request->unit,
            'kpi_per_hour'              => $request->kpi_per_hour,
            'sort_order'                => $request->sort_order,
            'is_active'                 => $request->is_active,
            'productivity_type'         => $request->productivity_type,
        ], fn ($v) => $v !== null);

        // Snapshot old KPI before update for change detection
        $oldKpi = isset($data['kpi_per_hour'])
            ? Department::where('id', $request->id)->value('kpi_per_hour')
            : null;

        $department = app(UpdateDepartmentTask::class)->run($request->id, $data);

        // Cascade kpi_per_hour to shift_details + hourly_records
        // Only for per_person / per_machine_dtf (NOT per_machine_dtg — uses machine-level KPI)
        if (
            $oldKpi !== null
            && (int) $oldKpi !== (int) $data['kpi_per_hour']
            && !$department->productivity_type?->isPerMachineDtg()
        ) {
            app(PropagateKpiToShiftDetailsTask::class)
                ->propagateDepartmentKpi($department->id, (int) $data['kpi_per_hour']);
        }

        return $department;
    }
}
