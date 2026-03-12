<?php

namespace App\Containers\AppSection\AuditLog\Supports;

use App\Containers\AppSection\AuditLog\Events\AuditHandlerEvent;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class AuditLogRecorder
{
    public static function recordModel(
        string $action,
        Model $model,
        ?string $referenceName = null,
        ?string $type = null
    ): void {
        $screen = Str::lower(class_basename($model));
        $referenceName ??= AuditLog::getReferenceName($screen, $model);
        $action = self::normalizeAction($action, $model);
        $type ??= self::resolveType($action);

        event(new AuditHandlerEvent(
            $model::class,
            $action,
            (string) $model->getKey(),
            (string) $referenceName,
            $type,
        ));
    }

    public static function record(
        string $module,
        string $action,
        string|int $referenceId = '',
        ?string $referenceName = null,
        ?string $type = null
    ): void {
        $type ??= self::resolveType($action);

        event(new AuditHandlerEvent(
            $module,
            $action,
            (string) $referenceId,
            (string) ($referenceName ?? ''),
            $type,
        ));
    }

    private static function normalizeAction(string $action, Model $model): string
    {
        if ($action !== 'updated' || ! $model instanceof User) {
            return $action;
        }

        $actorId = Auth::guard()->id() ?: Auth::guard('api')->id();
        if ($actorId && (int) $actorId === (int) $model->getKey()) {
            return 'has updated his profile';
        }

        return $action;
    }

    private static function resolveType(string $action): string
    {
        return match ($action) {
            'created', 'restored' => 'info',
            'updated', 'has updated his profile' => 'primary',
            'deleted', 'trashed', 'changed password' => 'danger',
            default => 'info',
        };
    }
}
