<?php

namespace App\Containers\AppSection\System\UI\API\Transformers;

use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class SystemInfoTransformer extends ParentTransformer
{
    /**
     * @param object|array<string, mixed> $payload
     */
    public function transform(object|array $payload): array
    {
        $data = (array) $payload;

        return [
            'type' => 'SystemInfo',
            'id' => 'system-info',
            'system_env' => $data['system_env'] ?? [],
            'server_env' => $data['server_env'] ?? [],
            'database_info' => $data['database_info'] ?? [],
            'requirements' => $data['requirements'] ?? [],
            'server_ip' => $data['server_ip'] ?? null,
        ];
    }
}
