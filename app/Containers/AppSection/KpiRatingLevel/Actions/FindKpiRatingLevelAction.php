<?php

namespace App\Containers\AppSection\KpiRatingLevel\Actions;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Containers\AppSection\KpiRatingLevel\Tasks\FindKpiRatingLevelByIdTask;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Requests\FindKpiRatingLevelRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class FindKpiRatingLevelAction extends ParentAction
{
    public function run(FindKpiRatingLevelRequest $request): KpiRatingLevel
    {
        return app(FindKpiRatingLevelByIdTask::class)->run($request->id);
    }
}
