<?php

namespace App\Containers\AppSection\Shift\UI\API\Transformers;

use App\Containers\AppSection\Shift\Models\ShiftDetailChange;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ShiftDetailChangeTransformer extends ParentTransformer
{
    public function transform(ShiftDetailChange $change): array
    {
        return [
            'id'         => $change->getHashedKey(),
            'user_name'  => $change->user_name,
            'changes'    => $change->changes,
            'ip_address' => $change->ip_address,
            'created_at' => $change->created_at->toIso8601String(),
        ];
    }
}
