<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Support\TargetEstimator;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

final class HourlyRecordTransformer extends ParentTransformer
{
    protected array $defaultIncludes = ['issues'];
    protected array $availableIncludes = ['hourlyMachines'];

    private ?CarbonImmutable $shiftDate = null;
    private ?Carbon $shiftEndAt = null;

    /**
     * Set the shift date for past-shift status override.
     * When shift date < today, all slots become 'completed'.
     */
    public function setShiftDate(?CarbonImmutable $shiftDate): self
    {
        $this->shiftDate = $shiftDate;

        return $this;
    }

    /**
     * Set the shift end datetime.
     * When now() >= shiftEndAt (same-day shift already ended), force 'completed'.
     */
    public function setShiftEndAt(?Carbon $shiftEndAt): self
    {
        $this->shiftEndAt = $shiftEndAt;

        return $this;
    }

    public function transform(HourlyRecord $record): array
    {
        $isPerMachineDtg = $record->department?->productivity_type?->isPerMachineDtg() ?? false;
        $isPerMachineDtf = $record->department?->productivity_type?->isPerMachineDtf() ?? false;
        $kpiPerHour      = $isPerMachineDtg
            ? ($record->shiftDetail?->kpi_per_hour ?? 0)
            : ($record->department?->kpi_per_hour ?? 0);
        // staff_required: always fallback to headcount (display purpose)
        $defaultStaffRequired = $record->shiftDetail?->headcount ?? 0;
        $staffRequired = $record->staff_required ?? $defaultStaffRequired;

        // Target multiplier: DTF uses machine_count, per_person uses staff_required
        // DTG: multiplier is ignored by TargetEstimator (isPerMachine=true → returns $base)
        $targetMultiplier = $isPerMachineDtf
            ? ($record->machine_count ?? $record->shiftDetail?->machine_count ?? 0)
            : $staffRequired;

        $target = TargetEstimator::effective(
            $record->target,
            $kpiPerHour,
            $record->kpi_percent ?? 100,
            $isPerMachineDtg,
            $targetMultiplier,
        );

        // ── Effective machine_count (with fallback for per_machine types) ──
        $effectiveMachineCount = $record->machine_count;
        if ($effectiveMachineCount === null && ($isPerMachineDtf || $isPerMachineDtg)) {
            $effectiveMachineCount = $record->shiftDetail?->machine_count;
        }

        // ── Active machines list with fallback (DTG only) ──
        $activeMachines = null;
        if ($isPerMachineDtg) {
            $hourlyMachines = $record->relationLoaded('hourlyMachines')
                ? $record->hourlyMachines
                : collect();

            if ($hourlyMachines->isNotEmpty()) {
                // Override: use hourly_record_machines
                $activeMachines = $hourlyMachines->map(fn ($hm) => [
                    'id'           => $hm->machine?->getHashedKey(),
                    'code'         => $hm->machine?->code,
                    'name'         => $hm->machine?->name,
                    'kpi_per_hour' => $hm->kpi_per_hour,
                ])->values()->all();
            } else {
                // Fallback: use shift_detail_machines
                $sdMachines = $record->shiftDetail?->machines ?? collect();
                $activeMachines = $sdMachines->map(fn ($sdm) => [
                    'id'           => $sdm->machine?->getHashedKey(),
                    'code'         => $sdm->machine?->code,
                    'name'         => $sdm->machine?->name,
                    'kpi_per_hour' => $sdm->kpi_per_hour,
                ])->values()->all();
            }
        }

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
            'efficiency' => $target > 0 && $record->actual > 0
                ? round(($record->actual / $target) * 100, 1)
                : 0,
            'error_rate' => $record->error_rate,
            'status'     => $this->resolveStatus($record->status),
            'note'              => $record->note,
            'productivity_json' => $record->productivity_json,
            'machine_count'     => $effectiveMachineCount,
            'productivity_type' => $record->department?->productivity_type?->value,
            'active_machines'   => $activeMachines,
            'is_machine_overridden' => $isPerMachineDtg && isset($hourlyMachines) && $hourlyMachines->isNotEmpty(),
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
     *
     * Two checks:
     *  1. Past date  — shiftDate < today()
     *  2. Same day but shift ended — now() >= shiftEndAt
     */
    private function resolveStatus(string $dbStatus): string
    {
        if ($this->shiftDate && $this->shiftDate->lt(today())) {
            return HourlyRecordStatus::Completed->value;
        }

        if ($this->shiftEndAt && now()->gte($this->shiftEndAt)) {
            return HourlyRecordStatus::Completed->value;
        }

        return $dbStatus;
    }
}
