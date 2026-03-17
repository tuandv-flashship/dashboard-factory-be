<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Containers\AppSection\ReasonCode\Tasks\UpdateReasonErrorTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\UpdateReasonErrorRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class UpdateReasonErrorAction extends ParentAction
{
    public function run(UpdateReasonErrorRequest $request): ReasonError
    {
        $data = array_filter([
            'category_id' => $request->category_id,
            'code'        => $request->code,
            'label'       => $request->label,
            'scope_dept'  => $request->scope_dept,
            'sort_order'  => $request->sort_order,
            'is_active'   => $request->is_active,
        ], fn ($v) => $v !== null);

        return app(UpdateReasonErrorTask::class)->run($request->id, $data);
    }
}
