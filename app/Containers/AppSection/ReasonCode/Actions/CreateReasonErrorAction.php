<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Containers\AppSection\ReasonCode\Tasks\CreateReasonErrorTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\CreateReasonErrorRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class CreateReasonErrorAction extends ParentAction
{
    public function __construct(
        private readonly CreateReasonErrorTask $task,
    ) {}

    public function run(CreateReasonErrorRequest $request): ReasonError
    {
        return $this->task->run([
            'category_id'  => $request->category_id,
            'sub_item_id'  => $request->sub_item_id,
            'code'         => $request->code,
            'label'        => $request->label,
            'sort_order'   => $request->sort_order ?? 0,
            'is_active'    => $request->is_active ?? true,
        ]);
    }
}

