<?php

namespace App\Containers\AppSection\Department\Actions;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Department\Tasks\UpdateDepartmentTask;
use App\Containers\AppSection\Department\UI\API\Requests\UpdateDepartmentRequest;
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

        return app(UpdateDepartmentTask::class)->run($request->id, $data);
    }
}
