<?php

namespace App\Containers\AppSection\Table\Tasks;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\Table\Supports\BulkActionRegistry;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

final class DispatchBulkChangeTask extends ParentTask
{
    public function __construct(
        private readonly BulkActionRegistry $registry,
    ) {
    }

    /**
     * @return array{success: int, failed: int, errors: array}
     */
    public function run(string $modelKey, array $ids, string $key, mixed $value): array
    {
        $model = $this->registry->resolveModel($modelKey);
        if (! $model) {
            return ['success' => 0, 'failed' => count($ids), 'errors' => [['reason' => 'invalid_model']]];
        }

        $customSave = $this->registry->resolveCustomSave($modelKey);
        $success = 0;
        $errors = [];

        foreach ($ids as $id) {
            try {
                /** @var Model $item */
                $item = $model->newQuery()->findOrFail($id);

                if ($customSave !== null) {
                    $customSave($item, $key, $value);
                } else {
                    $item->{$key} = $value;
                    $item->save();
                }

                AuditLogRecorder::recordModel('updated', $item);
                $success++;
            } catch (ModelNotFoundException) {
                $errors[] = ['id' => $id, 'reason' => 'not_found'];
            } catch (Throwable $e) {
                $errors[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        return [
            'success' => $success,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }
}
