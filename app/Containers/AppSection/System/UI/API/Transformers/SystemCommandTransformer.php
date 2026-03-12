<?php

namespace App\Containers\AppSection\System\UI\API\Transformers;

use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class SystemCommandTransformer extends ParentTransformer
{
    /**
     * @param array<string, mixed> $command
     */
    public function transform(mixed $command): array
    {
        if (is_object($command)) {
            $command = get_object_vars($command);
        }

        if (!is_array($command)) {
            $command = [];
        }

        return [
            'type' => 'SystemCommand',
            'id' => (string) ($command['action'] ?? ''),
            'action' => $command['action'] ?? null,
            'command' => $command['command'] ?? null,
            'options' => $command['options'] ?? [],
        ];
    }
}
