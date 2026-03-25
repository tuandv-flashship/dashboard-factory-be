<?php

namespace App\Containers\AppSection\KpiRatingLevel\Actions;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Containers\AppSection\KpiRatingLevel\Tasks\FindKpiRatingLevelByIdTask;
use App\Containers\AppSection\KpiRatingLevel\Tasks\GetActiveKpiRatingLevelTask;
use App\Containers\AppSection\KpiRatingLevel\Tasks\SyncKpiRatingLevelDetailsTask;
use App\Containers\AppSection\KpiRatingLevel\Tasks\UpdateKpiRatingLevelTask;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Requests\UpdateKpiRatingLevelRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class UpdateKpiRatingLevelAction extends ParentAction
{
    public function run(UpdateKpiRatingLevelRequest $request): KpiRatingLevel
    {
        $result = DB::transaction(function () use ($request) {
            // validated() only returns fields actually sent (respects 'sometimes' rules)
            // This preserves explicit nulls (e.g. effective_until=null) while ignoring absent fields
            $parentFields = ['name', 'effective_from', 'effective_until', 'description'];
            $data = array_intersect_key($request->validated(), array_flip($parentFields));

            $ratingLevel = app(UpdateKpiRatingLevelTask::class)->run($request->id, $data);

            if ($request->has('details')) {
                app(SyncKpiRatingLevelDetailsTask::class)->run(
                    $ratingLevel->id,
                    $request->details,
                );
            }

            return $ratingLevel->load('details');
        });

        GetActiveKpiRatingLevelTask::clearCache();

        return $result;
    }
}
