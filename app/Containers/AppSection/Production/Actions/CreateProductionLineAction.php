<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Production\Tasks\CreateProductionLineTask;
use App\Containers\AppSection\Production\UI\API\Requests\CreateProductionLineRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class CreateProductionLineAction extends ParentAction
{
    public function run(CreateProductionLineRequest $request): ProductionLine
    {
        return app(CreateProductionLineTask::class)->run([
            'code'       => $request->code,
            'label'      => $request->label,
            'color'      => $request->color,
            'subtitle'   => $request->subtitle,
            'sort_order' => $request->sort_order ?? 0,
            'is_active'  => $request->is_active ?? true,
        ]);
    }
}
