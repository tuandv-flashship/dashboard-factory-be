<?php

namespace App\Containers\AppSection\AuditLog\Actions;

use App\Containers\AppSection\AuditLog\Tasks\GetAuditLogWidgetTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Pagination\LengthAwarePaginator;

final class GetAuditLogWidgetAction extends ParentAction
{
    public function __construct(private readonly GetAuditLogWidgetTask $getAuditLogWidgetTask)
    {
    }

    public function run(): LengthAwarePaginator
    {
        return $this->getAuditLogWidgetTask->run();
    }
}
