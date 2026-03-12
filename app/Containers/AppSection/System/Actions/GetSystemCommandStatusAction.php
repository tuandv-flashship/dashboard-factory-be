<?php

namespace App\Containers\AppSection\System\Actions;

use App\Ship\Parents\Actions\Action as ParentAction;
use App\Ship\Supports\SystemCommandStore;

final class GetSystemCommandStatusAction extends ParentAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function run(string $jobId): ?array
    {
        return SystemCommandStore::get($jobId);
    }
}
