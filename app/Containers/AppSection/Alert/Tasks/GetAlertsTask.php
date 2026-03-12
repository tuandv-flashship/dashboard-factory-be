<?php

namespace App\Containers\AppSection\Alert\Tasks;

use App\Containers\AppSection\Alert\Models\Alert;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetAlertsTask extends ParentTask
{
    public function run(?string $line = null): Collection
    {
        $query = Alert::query()->unresolved()->latest('time');

        if ($line) {
            $query->forLine($line);
        }

        return $query->get();
    }
}
