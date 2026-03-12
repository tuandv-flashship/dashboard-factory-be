<?php

namespace App\Containers\AppSection\System\UI\API\Transformers;

use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class SystemCommandResultTransformer extends ParentTransformer
{
    /**
     * @param array<string, mixed> $result
     */
    public function transform(mixed $result): array
    {
        if (is_object($result)) {
            $result = get_object_vars($result);
        }

        if (!is_array($result)) {
            $result = [];
        }

        return [
            'type' => 'SystemCommandResult',
            'id' => (string) ($result['job_id'] ?? ($result['action'] ?? '')),
            'job_id' => $result['job_id'] ?? null,
            'action' => $result['action'] ?? null,
            'command' => $result['command'] ?? null,
            'status' => $result['status'] ?? null,
            'exit_code' => $result['exit_code'] ?? null,
            'output' => $result['output'] ?? null,
            'error' => $result['error'] ?? null,
            'started_at' => $result['started_at'] ?? null,
            'finished_at' => $result['finished_at'] ?? null,
            'created_at' => $result['created_at'] ?? null,
        ];
    }
}
