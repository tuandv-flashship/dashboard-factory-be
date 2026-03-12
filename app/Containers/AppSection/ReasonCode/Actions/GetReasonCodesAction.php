<?php

namespace App\Containers\AppSection\ReasonCode\Actions;

use App\Containers\AppSection\ReasonCode\Tasks\GetReasonCodesForContextTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Database\Eloquent\Collection;

final class GetReasonCodesAction extends ParentAction
{
    public function __construct(
        private readonly GetReasonCodesForContextTask $getReasonCodesTask,
    ) {
    }

    public function run(?string $line = null, ?string $dept = null): Collection
    {
        return $this->getReasonCodesTask->run($line, $dept);
    }
}
