<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\Department;
use App\Containers\AppSection\Production\Tasks\UpdateDepartmentTask;
use App\Containers\AppSection\Production\UI\API\Requests\UpdateDepartmentRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class UpdateDepartmentAction extends ParentAction
{
    public function run(UpdateDepartmentRequest $request): Department
    {
        $data = array_filter([
            'production_line_id' => $request->production_line_id,
            'code'               => $request->code,
            'label'              => $request->label,
            'label_en'           => $request->label_en,
            'icon'               => $request->icon,
            'unit'               => $request->unit,
            'kpi_per_hour'       => $request->kpi_per_hour,
            'factory'            => $request->factory,
            'sort_order'         => $request->sort_order,
            'is_active'          => $request->is_active,
        ], fn ($v) => $v !== null);

        return app(UpdateDepartmentTask::class)->run($request->id, $data);
    }
}
