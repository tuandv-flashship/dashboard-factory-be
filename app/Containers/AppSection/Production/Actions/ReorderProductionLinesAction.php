<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Production\UI\API\Requests\ReorderProductionLinesRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ReorderProductionLinesAction extends ParentAction
{
    public function run(ReorderProductionLinesRequest $request): void
    {
        foreach ($request->items as $item) {
            ProductionLine::where('id', $item['id'])->update([
                'sort_order' => $item['sort_order'],
            ]);
        }
    }
}
