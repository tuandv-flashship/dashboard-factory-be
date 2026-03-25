<?php

namespace App\Containers\AppSection\KpiRatingLevel\Actions;

use App\Containers\AppSection\KpiRatingLevel\Tasks\DeleteKpiRatingLevelTask;
use App\Containers\AppSection\KpiRatingLevel\Tasks\FindKpiRatingLevelByIdTask;
use App\Containers\AppSection\KpiRatingLevel\Tasks\GetActiveKpiRatingLevelTask;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Requests\DeleteKpiRatingLevelRequest;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteKpiRatingLevelAction extends ParentAction
{
    public function run(DeleteKpiRatingLevelRequest $request): bool
    {
        app(FindKpiRatingLevelByIdTask::class)->run($request->id);

        $result = app(DeleteKpiRatingLevelTask::class)->run($request->id);

        GetActiveKpiRatingLevelTask::clearCache();

        return $result;
    }
}
