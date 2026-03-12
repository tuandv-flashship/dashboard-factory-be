<?php

namespace App\Containers\AppSection\System\UI\API\Transformers;

use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class SystemAppSizeTransformer extends ParentTransformer
{
    /**
     * @param object|array<string, mixed> $payload
     */
    public function transform(object|array $payload): array
    {
        $data = (array) $payload;

        return [
            'type' => 'SystemAppSize',
            'id' => 'app-size',
            'app_size' => $data['app_size'] ?? null,
        ];
    }
}
