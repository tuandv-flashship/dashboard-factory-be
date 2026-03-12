<?php

namespace App\Containers\AppSection\AuditLog\Actions;

use App\Containers\AppSection\AuditLog\Tasks\ListAuditLogsTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListAuditLogsAction extends ParentAction
{
    public function __construct(private readonly ListAuditLogsTask $listAuditLogsTask)
    {
    }

    public function run(): LengthAwarePaginator
    {
        return $this->listAuditLogsTask->run();
    }
}
