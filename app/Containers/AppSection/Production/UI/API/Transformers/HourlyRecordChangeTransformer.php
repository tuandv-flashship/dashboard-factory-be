<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Production\Models\HourlyRecordChange;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class HourlyRecordChangeTransformer extends ParentTransformer
{
    public function transform(HourlyRecordChange $change): array
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
