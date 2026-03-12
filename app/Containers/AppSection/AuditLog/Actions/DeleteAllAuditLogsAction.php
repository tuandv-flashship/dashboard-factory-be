<?php

namespace App\Containers\AppSection\AuditLog\Actions;

use App\Containers\AppSection\AuditLog\Tasks\DeleteAllAuditLogsTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteAllAuditLogsAction extends ParentAction
{
    public function __construct(private readonly DeleteAllAuditLogsTask $deleteAllAuditLogsTask)
    {
    }

    public function run(): void
    {
        $this->deleteAllAuditLogsTask->run();
    }
}
