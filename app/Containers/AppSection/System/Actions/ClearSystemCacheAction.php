<?php

namespace App\Containers\AppSection\System\Actions;

use App\Containers\AppSection\System\Tasks\ClearSystemCacheTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ClearSystemCacheAction extends ParentAction
{
    public function __construct(private readonly ClearSystemCacheTask $clearSystemCacheTask)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(string $type): array
    {
        return $this->clearSystemCacheTask->run($type);
    }
}
