<?php

namespace App\Containers\AppSection\Shift\UI\API\Transformers;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class HourlyRecordTransformer extends ParentTransformer
{
    protected array $defaultIncludes = [];
    protected array $availableIncludes = [];

    public function transform(HourlyRecord $record): array
    {
        $dept = $record->relationLoaded('department') ? $record->department : null;

        return [
            'id'              => $record->getHashedKey(),
            'department_id'   => $dept?->getHashedKey(),
            'department_code' => $dept?->code,
            'department_label'=> $dept?->label,
            'hour_slot'       => $record->hour_slot,
            'hour_index'      => $record->hour_index,
            'staff'           => $record->staff,
            'target'          => $record->target,
            'actual'          => $record->actual,
            'remaining'       => $record->actual !== null
                ? $record->target - $record->actual
                : null,
            'efficiency'      => $record->efficiency,
            'error_rate'      => $record->error_rate,
        ];
    }
}
