<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Production\Models\PickHourlyRecord;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class PickHourlyRecordTransformer extends ParentTransformer
{
    public function transform(PickHourlyRecord $record): array
    {
        return [
            'id' => $record->getHashedKey(),
            'hour_slot' => $record->hour_slot,
            'hour_index' => $record->hour_index,
            'target' => $record->target,
            'actual' => $record->actual,
            'missed' => $record->actual !== null && $record->actual < $record->target,
            'staff' => $record->staff,
            'efficiency' => $record->efficiency,
            'error_rate' => $record->error_rate,
            'total_picked' => $record->total_picked,
        ];
    }
}
