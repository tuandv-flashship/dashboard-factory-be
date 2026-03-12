<?php

namespace App\Containers\AppSection\Quality\Actions;

use App\Containers\AppSection\Quality\Models\QualityRecord;
use App\Containers\AppSection\Quality\Tasks\GetQualityDataTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetQualityDataAction extends ParentAction
{
    public function __construct(
        private readonly GetQualityDataTask $task,
    ) {
    }

    public function run(?string $date = null, ?int $shiftNumber = null): QualityRecord|null
    {
        return $this->task->run($date, $shiftNumber);
    }
}
