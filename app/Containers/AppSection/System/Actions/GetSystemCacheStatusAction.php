<?php

namespace App\Containers\AppSection\System\Actions;

use App\Containers\AppSection\System\Tasks\GetSystemCacheStatusTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetSystemCacheStatusAction extends ParentAction
{
    public function __construct(private readonly GetSystemCacheStatusTask $getSystemCacheStatusTask)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return $this->getSystemCacheStatusTask->run();
    }
}
