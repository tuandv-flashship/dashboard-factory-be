<?php

namespace App\Containers\AppSection\Table\BulkActions;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\Table\Abstracts\BulkActionAbstract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

/**
 * Deletes records one-by-one so model boot events (relation cleanup, slug deletion) are triggered.
 * Mirrors Botble's DeleteBulkAction.
 */
final class DeleteBulkAction extends BulkActionAbstract
{
    public function getActionKey(): string
    {
        return 'delete';
    }

    public function getLabel(): string
    {
        return trans('table::actions.delete_selected');
    }

    public function getIcon(): string
    {
        return 'ti-trash';
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function getPriority(): int
    {
        return 99;
    }

    public function getConfirmation(): array
    {
        return [
            'title' => trans('table::actions.confirm_bulk_delete_title'),
            'message' => trans('table::actions.confirm_bulk_delete_message'),
            'confirm_button' => [
                'label' => trans('table::actions.delete_all'),
                'color' => 'danger',
                'icon' => 'ti-trash',
            ],
            'cancel_button' => [
                'label' => trans('table::actions.cancel'),
                'color' => 'secondary',
            ],
        ];
    }

    public function dispatch(Model $model, array $ids): array
    {
        $this->handleBeforeDispatch($model, $ids);

        $success = 0;
        $errors = [];

        foreach ($ids as $id) {
            try {
                $item = $model->newQuery()->findOrFail($id);
                $item->delete();

                AuditLogRecorder::recordModel('deleted', $item);
                $success++;
            } catch (ModelNotFoundException) {
                $errors[] = ['id' => $id, 'reason' => 'not_found'];
            } catch (Throwable $e) {
                $errors[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $this->handleAfterDispatch($model, $ids);

        return [
            'success' => $success,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }
}
