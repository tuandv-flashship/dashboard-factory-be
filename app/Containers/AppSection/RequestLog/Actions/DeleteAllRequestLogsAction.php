<?php

namespace App\Containers\AppSection\RequestLog\Actions;

use App\Containers\AppSection\RequestLog\Tasks\DeleteAllRequestLogsTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteAllRequestLogsAction extends ParentAction
{
    public function __construct(private readonly DeleteAllRequestLogsTask $deleteAllRequestLogsTask)
    {
    }

    public function run(): void
    {
        $this->deleteAllRequestLogsTask->run();
    }
}
