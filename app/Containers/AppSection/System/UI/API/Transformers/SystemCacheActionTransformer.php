<?php

namespace App\Containers\AppSection\System\UI\API\Transformers;

use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class SystemCacheActionTransformer extends ParentTransformer
{
    /**
     * @param object|array<string, mixed> $payload
     */
    public function transform(object|array $payload): array
    {
        $data = (array) $payload;

        return [
            'type' => 'SystemCacheAction',
            'id' => (string) ($data['type'] ?? ''),
            'action' => $data['type'] ?? null,
            'success' => (bool) ($data['success'] ?? false),
            'message' => $data['message'] ?? null,
            'details' => $data['details'] ?? [],
        ];
    }
}
