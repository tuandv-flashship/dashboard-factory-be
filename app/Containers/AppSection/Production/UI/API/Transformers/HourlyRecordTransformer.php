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
            'kpi_hours'   => $record->kpi_hours,
            'kpi_minutes' => $record->kpi_minutes,
            'kpi_percent' => $record->kpi_percent,
            'actual' => $record->actual,
            'missed' => $record->actual !== null && $record->actual < $record->target,
            'staff' => $record->staff,
            'staff_required' => $record->staff_required,
            'hour_start_inventory' => $record->hour_start_inventory,
            'efficiency' => $record->efficiency,
            'error_rate' => $record->error_rate,
            'status'     => $record->status,
            'note'              => $record->note,
            'productivity_json' => $record->productivity_json,
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
