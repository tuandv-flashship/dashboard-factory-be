<?php

namespace App\Containers\AppSection\System\UI\API\Transformers;

use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class SystemCacheStatusTransformer extends ParentTransformer
{
    /**
     * @param object|array<string, mixed> $payload
     */
    public function transform(object|array $payload): array
    {
        $data = (array) $payload;

        return [
            'type' => 'SystemCacheStatus',
            'id' => 'cache-status',
            'cache_size_bytes' => $data['cache_size_bytes'] ?? 0,
            'cache_size' => $data['cache_size'] ?? '0 B',
            'types' => $data['types'] ?? [],
        ];
    }
}
