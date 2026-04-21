<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Containers\AppSection\ReasonCode\Tasks\UpdateReasonCategoryTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\UpdateReasonCategoryRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class UpdateReasonCategoryAction extends ParentAction
{
    public function __construct(
        private readonly UpdateReasonCategoryTask $task,
    ) {}

    public function run(UpdateReasonCategoryRequest $request): ReasonCategory
    {
        $data = array_filter([
            'code'       => $request->code,
            'label'      => $request->label,
            'label_en'   => $request->label_en,
            'icon'       => $request->icon,
            'color'      => $request->color,
            'sort_order' => $request->sort_order,
            'is_active'  => $request->is_active,
        ], fn ($v) => $v !== null);

        return $this->task->run($request->id, $data);
    }
}
