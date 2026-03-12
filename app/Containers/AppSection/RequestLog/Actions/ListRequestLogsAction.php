<?php

namespace App\Containers\AppSection\RequestLog\Actions;

use App\Containers\AppSection\RequestLog\Tasks\ListRequestLogsTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListRequestLogsAction extends ParentAction
{
    public function __construct(private readonly ListRequestLogsTask $listRequestLogsTask)
    {
    }

    public function run(): LengthAwarePaginator
    {
        return $this->listRequestLogsTask->run();
    }
}
