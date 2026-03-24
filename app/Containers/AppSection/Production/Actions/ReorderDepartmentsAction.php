<?php

namespace App\Containers\AppSection\Production\Actions;

use App\Containers\AppSection\Production\Models\Department;
use App\Containers\AppSection\Production\UI\API\Requests\ReorderDepartmentsRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ReorderDepartmentsAction extends ParentAction
{
    public function run(ReorderDepartmentsRequest $request): void
    {
        foreach ($request->items as $item) {
            Department::where('id', $item['id'])->update([
                'sort_order' => $item['sort_order'],
            ]);
        }
    }
}
