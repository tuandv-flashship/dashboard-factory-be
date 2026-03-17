<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Containers\AppSection\ReasonCode\Tasks\CreateReasonErrorTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\CreateReasonErrorRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class CreateReasonErrorAction extends ParentAction
{
    public function run(CreateReasonErrorRequest $request): ReasonError
    {
        return app(CreateReasonErrorTask::class)->run([
            'category_id' => $request->category_id,
            'code'        => $request->code,
            'label'       => $request->label,
            'scope_dept'  => $request->scope_dept,
            'sort_order'  => $request->sort_order ?? 0,
            'is_active'   => $request->is_active ?? true,
        ]);
    }
}
