<?php

namespace App\Containers\AppSection\Table\Actions;

use App\Containers\AppSection\Table\Supports\BulkActionRegistry;
use App\Containers\AppSection\Table\Tasks\SaveColumnVisibilityTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Contracts\Auth\Authenticatable;

final class SaveColumnVisibilityAction extends ParentAction
{
    public function __construct(
        private readonly SaveColumnVisibilityTask $task,
        private readonly BulkActionRegistry $registry,
    ) {
    }

    public function run(Authenticatable $user, string $modelKey, array $columns): void
    {
        $this->task->run($user, $modelKey, $columns);

        // Invalidate table meta cache since column visibility changed
        $this->registry->invalidateCache($modelKey, $user);
    }
}
