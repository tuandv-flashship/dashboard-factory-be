<?php

namespace App\Containers\AppSection\Table\Actions;

use App\Containers\AppSection\Table\Events\BulkActionCompleted;
use App\Containers\AppSection\Table\Supports\BulkActionRegistry;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DispatchBulkActionAction extends ParentAction
{
    public function __construct(
        private readonly BulkActionRegistry $registry,
    ) {
    }

    public function run(string $modelKey, string $actionKey, array $ids): array
    {
        $model = $this->registry->resolveModel($modelKey);
        abort_if(! $model, 422, "Invalid model key: {$modelKey}");

        $bulkAction = $this->registry->resolveBulkAction($modelKey, $actionKey);
        abort_if(! $bulkAction, 422, "Invalid action: {$actionKey}");

        $result = $bulkAction->dispatch($model, $ids);

        // Fire event
        $successIds = array_diff($ids, array_column($result['errors'], 'id'));
        event(new BulkActionCompleted(
            $actionKey,
            $modelKey,
            array_values($successIds),
            $result['success'],
            $result['failed'],
        ));

        return $result;
    }
}
