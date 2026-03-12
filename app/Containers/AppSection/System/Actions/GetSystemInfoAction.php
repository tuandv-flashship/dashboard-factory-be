<?php

namespace App\Containers\AppSection\System\Actions;

use App\Containers\AppSection\System\Tasks\GetSystemInfoTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetSystemInfoAction extends ParentAction
{
    public function __construct(private readonly GetSystemInfoTask $getSystemInfoTask)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return $this->getSystemInfoTask->run();
    }
}
