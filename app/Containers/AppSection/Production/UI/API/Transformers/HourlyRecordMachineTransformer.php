<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Production\Models\HourlyRecordMachine;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class HourlyRecordMachineTransformer extends ParentTransformer
{
    public function transform(HourlyRecordMachine $pivot): array
    {
        $machine = $pivot->machine;

        return [
            'machine_id'   => $machine?->getHashedKey(),
            'code'         => $machine?->code,
            'name'         => $machine?->name,
            'kpi_per_hour' => $pivot->kpi_per_hour,
        ];
    }
}
