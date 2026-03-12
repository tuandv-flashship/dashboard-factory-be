<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Production\Models\Shift;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ShiftTransformer extends ParentTransformer
{
    public function transform(Shift $shift): array
    {
        return [
            'id' => $shift->getHashedKey(),
            'date' => $shift->date->toDateString(),
            'shift_number' => $shift->shift_number,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'supervisor' => $shift->supervisor,
            'is_active' => $shift->is_active,
        ];
    }
}
