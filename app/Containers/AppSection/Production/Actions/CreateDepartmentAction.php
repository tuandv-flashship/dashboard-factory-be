<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\Department;
use App\Containers\AppSection\Production\Tasks\CreateDepartmentTask;
use App\Containers\AppSection\Production\UI\API\Requests\CreateDepartmentRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class CreateDepartmentAction extends ParentAction
{
    public function run(CreateDepartmentRequest $request): Department
    {
        return app(CreateDepartmentTask::class)->run([
            'production_line_id' => $request->production_line_id,
            'code'               => $request->code,
            'label'              => $request->label,
            'label_en'           => $request->label_en,
            'icon'               => $request->icon,
            'unit'               => $request->unit,
            'kpi_per_hour'       => $request->kpi_per_hour ?? 0,
            'factory'            => $request->factory,
            'sort_order'         => $request->sort_order ?? 0,
            'is_active'          => $request->is_active ?? true,
        ]);
    }
}
