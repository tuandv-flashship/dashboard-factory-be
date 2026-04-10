<?php

namespace App\Containers\AppSection\Department\Actions;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Department\Tasks\CreateDepartmentTask;
use App\Containers\AppSection\Department\UI\API\Requests\CreateDepartmentRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Str;

final class CreateDepartmentAction extends ParentAction
{
    public function run(CreateDepartmentRequest $request): Department
    {
        return app(CreateDepartmentTask::class)->run([
            'production_line_id'        => $request->production_line_id,
            'code'                      => Str::slug($request->name),
            'label'                     => $request->name,
            'label_en'                  => $request->name,
            'description'               => $request->description,
            'icon'                      => 'Layers',
            'unit'                      => $request->unit,
            'kpi_per_hour'              => $request->kpi_per_hour ?? 0,
            'sort_order'                => $request->sort_order ?? 0,
            'is_active'                 => true,
            'productivity_type'         => $request->productivity_type ?? 'per_person',
        ]);
    }
}
