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
            'production_line_id'        => $request->group,
            'code'                      => Str::slug($request->name),
            'label'                     => $request->name,
            'label_en'                  => $request->name,
            'description'               => $request->description,
            'icon'                      => 'Layers',
            'unit'                      => $request->unit,
            'kpi_per_hour'              => $request->kpi_per_hour ?? 0,
            'factory'                   => $request->factory,
            'sort_order'                => $request->sort_order ?? 0,
            'is_active'                 => true,
            'can_increase_productivity' => $request->can_increase_productivity ?? true,
        ]);
    }
}
