<?php

namespace App\Containers\AppSection\Machine\UI\API\Transformers;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class MachineTransformer extends ParentTransformer
{
    public function transform(Machine $machine): array
    {
        return [
            'id' => $machine->getHashedKey(),
            'code' => $machine->code,
            'name' => $machine->name,
            'status' => $machine->status,
            'department' => $machine->department,
            'line' => $machine->line,
            'description' => $machine->description,
        ];
    }
}
