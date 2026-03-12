<?php

namespace App\Containers\AppSection\AuditLog\Supports;

use Illuminate\Database\Eloquent\Model;

final class AuditLog
{
    public static function getReferenceName(string $screen, Model $data): string
    {
        $attributes = $data->getAttributes();
        $name = $attributes['name'] ?? null;
        $title = $attributes['title'] ?? null;

        if (in_array($screen, ['user', 'auth'], true)) {
            return (string) ($name ?? $title ?? '');
        }

        $reference = $name ?? $title;
        if ($reference !== null && $reference !== '') {
            return (string) $reference;
        }

        $keyName = $data->getKeyName();
        if (array_key_exists($keyName, $attributes) && $attributes[$keyName] !== null) {
            return 'ID: ' . $attributes[$keyName];
        }

        return '';
    }
}
