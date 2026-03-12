<?php

namespace App\Containers\AppSection\System\Actions;

use App\Containers\AppSection\System\Tasks\GetSystemAppSizeTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetSystemAppSizeAction extends ParentAction
{
    public function __construct(private readonly GetSystemAppSizeTask $getSystemAppSizeTask)
    {
    }

    public function run(): string
    {
        return $this->getSystemAppSizeTask->run();
    }
}
