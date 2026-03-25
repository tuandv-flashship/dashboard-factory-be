<?php

namespace App\Containers\AppSection\KpiRatingLevel\Actions;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Containers\AppSection\KpiRatingLevel\Tasks\GetActiveKpiRatingLevelTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetActiveKpiRatingLevelAction extends ParentAction
{
    public function run(): KpiRatingLevel|array
    {
        return app(GetActiveKpiRatingLevelTask::class)->run();
    }
}
