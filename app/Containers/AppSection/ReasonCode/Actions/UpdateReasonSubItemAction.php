<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Models\ReasonSubItem;
use App\Containers\AppSection\ReasonCode\Tasks\UpdateReasonSubItemTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\UpdateReasonSubItemRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class UpdateReasonSubItemAction extends ParentAction
{
    public function run(UpdateReasonSubItemRequest $request): ReasonSubItem
    {
        $data = array_filter([
            'category_id' => $request->category_id,
            'code'        => $request->code,
            'label'       => $request->label,
            'scope_type'  => $request->scope_type,
            'scope_line'  => $request->scope_line,
            'scope_dept'  => $request->scope_dept,
            'sort_order'  => $request->sort_order,
            'is_active'   => $request->is_active,
        ], fn ($v) => $v !== null);

        return app(UpdateReasonSubItemTask::class)->run($request->id, $data);
    }
}
