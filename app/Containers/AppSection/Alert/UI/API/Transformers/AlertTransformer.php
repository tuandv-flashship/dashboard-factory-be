<?php

namespace App\Containers\AppSection\Alert\UI\API\Transformers;

use App\Containers\AppSection\Alert\Models\Alert;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class AlertTransformer extends ParentTransformer
{
    public function transform(Alert $alert): array
    {
        return [
            'id' => $alert->getHashedKey(),
            'severity' => $alert->severity,
            'department' => $alert->department,
            'time' => $alert->time,
            'message' => $alert->message,
            'line' => $alert->line,
            'is_resolved' => $alert->is_resolved,
        ];
    }
}
