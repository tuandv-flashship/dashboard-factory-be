<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Containers\AppSection\ReasonCode\Tasks\CreateReasonCategoryTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\CreateReasonCategoryRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class CreateReasonCategoryAction extends ParentAction
{
    public function run(CreateReasonCategoryRequest $request): ReasonCategory
    {
        return app(CreateReasonCategoryTask::class)->run([
            'code'       => $request->code,
            'label'      => $request->label,
            'label_en'   => $request->label_en,
            'icon'       => $request->icon,
            'color'      => $request->color,
            'sort_order' => $request->sort_order ?? 0,
            'is_active'  => $request->is_active ?? true,
        ]);
    }
}
