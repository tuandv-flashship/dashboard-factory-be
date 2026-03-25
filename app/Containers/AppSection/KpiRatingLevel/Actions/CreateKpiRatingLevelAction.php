<?php

namespace App\Containers\AppSection\KpiRatingLevel\Actions;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Containers\AppSection\KpiRatingLevel\Tasks\CreateKpiRatingLevelTask;
use App\Containers\AppSection\KpiRatingLevel\Tasks\GetActiveKpiRatingLevelTask;
use App\Containers\AppSection\KpiRatingLevel\Tasks\SyncKpiRatingLevelDetailsTask;
use App\Containers\AppSection\KpiRatingLevel\UI\API\Requests\CreateKpiRatingLevelRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class CreateKpiRatingLevelAction extends ParentAction
{
    public function run(CreateKpiRatingLevelRequest $request): KpiRatingLevel
    {
        $result = DB::transaction(function () use ($request) {
            $ratingLevel = app(CreateKpiRatingLevelTask::class)->run([
                'name'            => $request->name,
                'effective_from'  => $request->effective_from,
                'effective_until' => $request->effective_until,
                'description'     => $request->description,
            ]);

            app(SyncKpiRatingLevelDetailsTask::class)->run(
                $ratingLevel->id,
                $request->details,
            );

            return $ratingLevel->load('details');
        });

        GetActiveKpiRatingLevelTask::clearCache();

        return $result;
    }
}
