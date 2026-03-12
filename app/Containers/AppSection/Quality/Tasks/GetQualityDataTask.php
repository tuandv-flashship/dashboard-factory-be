<?php

namespace App\Containers\AppSection\Quality\Tasks;

use App\Containers\AppSection\Quality\Models\QualityRecord;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class GetQualityDataTask extends ParentTask
{
    public function run(?string $date = null, ?int $shiftNumber = null): QualityRecord|null
    {
        return QualityRecord::resolve($date, $shiftNumber);
    }
}
