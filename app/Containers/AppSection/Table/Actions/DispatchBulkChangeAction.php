<?php

namespace App\Containers\AppSection\Table\Actions;

use App\Containers\AppSection\Table\Events\BulkChangeCompleted;
use App\Containers\AppSection\Table\Supports\BulkActionRegistry;
use App\Containers\AppSection\Table\Tasks\DispatchBulkChangeTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DispatchBulkChangeAction extends ParentAction
{
    public function __construct(
        private readonly BulkActionRegistry $registry,
        private readonly DispatchBulkChangeTask $task,
    ) {
    }

    public function run(string $modelKey, array $ids, string $key, mixed $value): array
    {
        // Validate the change key exists for this model
        $bulkChange = $this->registry->resolveBulkChange($modelKey, $key);
        abort_if(! $bulkChange, 422, "Invalid bulk change key: {$key}");

        // Validate the value against the change's rules
        $rules = $bulkChange->getValidationRules();
        if (! empty($rules)) {
            validator(['value' => $value], ['value' => $rules])->validate();
        }

        $result = $this->task->run($modelKey, $ids, $key, $value);

        // Fire event
        $successIds = array_diff($ids, array_column($result['errors'], 'id'));
        event(new BulkChangeCompleted(
            $modelKey,
            $key,
            $value,
            array_values($successIds),
            $result['success'],
            $result['failed'],
        ));

        return $result;
    }
}
