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
            'description' => $machine->description,
            'unit' => $machine->unit,
            'kpi_per_hour' => $machine->kpi_per_hour,
            'sort_order' => $machine->sort_order,
            'is_active' => $machine->is_active,
        ];
    }
}
