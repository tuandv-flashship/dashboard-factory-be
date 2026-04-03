<?php

namespace App\Containers\AppSection\KpiRatingLevel\Actions;

use App\Containers\AppSection\KpiRatingLevel\Enums\KpiRatingLevelStatus;
use App\Containers\AppSection\KpiRatingLevel\Tasks\ListKpiRatingLevelsTask;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Requests\ListKpiRatingLevelsRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListKpiRatingLevelsAction extends ParentAction
{
    public function run(ListKpiRatingLevelsRequest $request): LengthAwarePaginator
    {
        $status = $request->validated('status')
            ? KpiRatingLevelStatus::from($request->validated('status'))
            : null;

        return app(ListKpiRatingLevelsTask::class)->run($status);
    }
}
