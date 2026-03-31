<?php

namespace App\Containers\AppSection\KpiRatingLevel\Actions;

use App\Ship\Parents\Actions\Action as ParentAction;

final class GetDefaultKpiRatingLevelAction extends ParentAction
{
    public function run(): array
    {
        return config('appSection-kpiRatingLevel.default');
    }
}
