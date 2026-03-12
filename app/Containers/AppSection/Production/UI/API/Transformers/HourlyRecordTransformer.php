<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class HourlyRecordTransformer extends ParentTransformer
{
    protected array $defaultIncludes = ['issues'];

    public function transform(HourlyRecord $record): array
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
        ];
    }

    public function includeIssues(HourlyRecord $record): \League\Fractal\Resource\Collection
    {
        return $this->collection(
            $record->issues,
            new HourlyIssueTransformer(),
            'issues',
        );
    }
}
