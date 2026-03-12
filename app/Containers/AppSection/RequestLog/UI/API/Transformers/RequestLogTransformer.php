<?php

namespace App\Containers\AppSection\RequestLog\UI\API\Transformers;

use App\Containers\AppSection\RequestLog\Models\RequestLog;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class RequestLogTransformer extends ParentTransformer
{
    public function transform(RequestLog $log): array
    {
        return [
            'type' => $log->getResourceKey(),
            'id' => $log->getHashedKey(),
            'url' => $log->url,
            'status_code' => $log->status_code,
            'count' => $log->count,
            'referrer' => $log->referrer,
            'user_id' => $log->user_id,
            'created_at' => $log->created_at?->toISOString(),
            'updated_at' => $log->updated_at?->toISOString(),
        ];
    }
}
