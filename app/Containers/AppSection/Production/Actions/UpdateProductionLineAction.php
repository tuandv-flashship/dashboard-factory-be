<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Production\Tasks\UpdateProductionLineTask;
use App\Containers\AppSection\Production\UI\API\Requests\UpdateProductionLineRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class UpdateProductionLineAction extends ParentAction
{
    public function run(UpdateProductionLineRequest $request): ProductionLine
    {
        $data = array_filter([
            'code'       => $request->code,
            'label'      => $request->label,
            'color'      => $request->color,
            'subtitle'   => $request->subtitle,
            'is_shared'  => $request->is_shared,
            'sort_order' => $request->sort_order,
            'is_active'  => $request->is_active,
        ], fn ($v) => $v !== null);

        return app(UpdateProductionLineTask::class)->run($request->id, $data);
    }
}
