<?php

namespace App\Containers\AppSection\Department\Actions;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Department\UI\API\Requests\ReorderDepartmentsRequest;
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
