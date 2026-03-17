<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Containers\AppSection\ReasonCode\Tasks\FindReasonCategoryByIdTask;
use App\Containers\AppSection\ReasonCode\UI\API\Requests\FindReasonCategoryRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class FindReasonCategoryAction extends ParentAction
{
    public function run(FindReasonCategoryRequest $request): ReasonCategory
    {
        return app(FindReasonCategoryByIdTask::class)->run($request->id);
    }
}
