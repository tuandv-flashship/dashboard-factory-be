<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Models\ReasonSubItem;
use App\Containers\AppSection\ReasonCode\Tasks\CreateReasonSubItemTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\CreateReasonSubItemRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class CreateReasonSubItemAction extends ParentAction
{
    public function run(CreateReasonSubItemRequest $request): ReasonSubItem
    {
        return app(CreateReasonSubItemTask::class)->run([
            'category_id' => $request->category_id,
            'code'        => $request->code,
            'label'       => $request->label,
            'scope_type'  => $request->scope_type,
            'scope_line'  => $request->scope_line,
            'scope_dept'  => $request->scope_dept,
            'sort_order'  => $request->sort_order ?? 0,
            'is_active'   => $request->is_active ?? true,
        ]);
    }
}
