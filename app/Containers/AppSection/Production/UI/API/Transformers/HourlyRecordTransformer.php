<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class HourlyRecordTransformer extends ParentTransformer
{
    protected array $defaultIncludes = ['issues'];

    public function transform(HourlyRecord $record): array
    {
        $target         = $record->target ?? $this->estimateTarget($record);
        $staffRequired  = $record->staff_required ?? $record->shiftDetail?->headcount;

        return [
            'id' => $record->getHashedKey(),
            'hour_slot' => $record->hour_slot,
            'hour_index' => $record->hour_index,
            'target' => $target,
            'kpi_hours'   => $record->kpi_hours,
            'kpi_minutes' => $record->kpi_minutes,
            'kpi_percent' => $record->kpi_percent,
            'actual' => $record->actual,
            'missed' => $record->actual !== null && $target !== null && $record->actual < $target,
            'staff' => $record->staff,
            'staff_required' => $staffRequired,
            'hour_start_inventory' => $record->hour_start_inventory,
            'efficiency' => $record->efficiency,
            'error_rate' => $record->error_rate,
            'status'     => $record->status,
            'note'              => $record->note,
            'productivity_json' => $record->productivity_json,
        ];
    }

    /**
     * Estimate target when not yet set:
     * kpi_per_hour × kpi_percent / 100 × staff_required.
     */
    private function estimateTarget(HourlyRecord $record): int
    {
        $kpiPerHour    = $record->shiftDetail?->kpi_per_hour
            ?? $record->department?->kpi_per_hour
            ?? 0;
        $kpiPercent    = $record->kpi_percent ?? 100;
        $staffRequired = $record->staff_required
            ?? $record->shiftDetail?->headcount
            ?? 0;

        return (int) round($kpiPerHour * $kpiPercent / 100 * $staffRequired);
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
