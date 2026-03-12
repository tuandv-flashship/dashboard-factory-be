<?php

namespace App\Containers\AppSection\AuditLog\Actions;

use App\Containers\AppSection\AuditLog\Tasks\DeleteAuditLogTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteAuditLogAction extends ParentAction
{
    public function __construct(private readonly DeleteAuditLogTask $deleteAuditLogTask)
    {
    }

    public function run(int $id): void
    {
        $this->deleteAuditLogTask->run($id);
    }
}
