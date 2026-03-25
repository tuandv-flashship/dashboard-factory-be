<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Tasks\ListShiftTemplatesTask;
use App\Containers\AppSection\Shift\UI\API\Requests\ListShiftTemplatesRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListShiftTemplatesAction extends ParentAction
{
    public function run(ListShiftTemplatesRequest $request): LengthAwarePaginator
    {
        return app(ListShiftTemplatesTask::class)->run();
    }
}
