<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Tasks\UpdateHourlyStaffTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class UpdateHourlyStaffAction extends ParentAction
{
    public function run(array $records): void
    {
        DB::transaction(function () use ($records) {
            app(UpdateHourlyStaffTask::class)->run($records);
        });
    }
}
