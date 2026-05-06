<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Support\TargetEstimator;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;
use Carbon\CarbonImmutable;

final class HourlyRecordTransformer extends ParentTransformer
{
    protected array $defaultIncludes = ['issues'];
    protected array $availableIncludes = ['hourlyMachines'];

    private ?CarbonImmutable $shiftDate = null;

    /**
     * Set the shift date for past-shift status override.
     * When shift date < today, all slots become 'completed'.
     */
    public function setShiftDate(?CarbonImmutable $shiftDate): self
    {
        $this->shiftDate = $shiftDate;

        return $this;
    }

    public function transform(HourlyRecord $record): array
    {
        $isPerMachineDtg = $record->department?->productivity_type?->isPerMachineDtg() ?? false;
        $isPerMachineDtf = $record->department?->productivity_type?->isPerMachineDtf() ?? false;
        $kpiPerHour      = $isPerMachineDtg
            ? ($record->shiftDetail?->kpi_per_hour ?? 0)
            : ($record->department?->kpi_per_hour ?? 0);
        // DTF: fallback chain → hourly.machine_count → shift_detail.machine_count
        // DTG: multiplier is ignored by TargetEstimator (isPerMachine=true → returns $base)
        $defaultMultiplier = $isPerMachineDtf
            ? ($record->machine_count ?? $record->shiftDetail?->machine_count ?? 0)
            : ($record->shiftDetail?->headcount ?? 0);
        $staffRequired   = $record->staff_required ?? $defaultMultiplier;

        $target = TargetEstimator::effective(
            $record->target,
            $kpiPerHour,
            $record->kpi_percent ?? 100,
            $isPerMachineDtg,
            $staffRequired,
        );

        return [
            'id' => $record->getHashedKey(),
            'hour_slot' => $record->hour_slot,
            'hour_index' => $record->hour_index,
            'target' => $target,
            'kpi_hours'   => $record->kpi_hours,
            'kpi_minutes' => $record->kpi_minutes,
            'kpi_percent' => $record->kpi_percent,
            'actual' => $record->actual,
            'missed' => $record->actual !== null && $target > 0 && $record->actual < $target,
            'staff' => $record->staff,
            'staff_required' => $staffRequired,
            'hour_start_inventory' => $record->hour_start_inventory,
            'efficiency' => $record->efficiency,
            'error_rate' => $record->error_rate,
            'status'     => $this->resolveStatus($record->status),
            'note'              => $record->note,
            'productivity_json' => $record->productivity_json,
            'machine_count'     => $record->machine_count,
            'productivity_type' => $record->department?->productivity_type?->value,
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

    public function includeHourlyMachines(HourlyRecord $record): \League\Fractal\Resource\Collection
    {
        return $this->collection(
            $record->hourlyMachines,
            new HourlyRecordMachineTransformer(),
            'hourly_machines',
        );
    }

    /**
     * Past shifts → force 'completed'; otherwise use DB value.
     */
    private function resolveStatus(string $dbStatus): string
    {
        if ($this->shiftDate && $this->shiftDate->lt(today())) {
            return HourlyRecordStatus::Completed->value;
        }

        return $dbStatus;
    }
}
