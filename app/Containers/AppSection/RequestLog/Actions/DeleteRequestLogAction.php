<?php

namespace App\Containers\AppSection\RequestLog\Actions;

use App\Containers\AppSection\RequestLog\Tasks\DeleteRequestLogTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteRequestLogAction extends ParentAction
{
    public function __construct(private readonly DeleteRequestLogTask $deleteRequestLogTask)
    {
    }

    public function run(int $id): void
    {
        $this->deleteRequestLogTask->run($id);
    }
}
