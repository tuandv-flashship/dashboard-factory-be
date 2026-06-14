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
    private CarbonImmutable|Carbon|null $now = null;

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

        // Always use shift_detail snapshot KPI (point-in-time value when shift was created).
        // Fallback to department only if shiftDetail is not available.
        // Previously, per_person/dtf used department->kpi_per_hour (live value),
        // causing historical shifts to show wrong target after department KPI changes.
        $kpiPerHour = $record->shiftDetail?->kpi_per_hour
            ?? ($record->department?->kpi_per_hour ?? 0);
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
            'is_out_of_work' => $record->hour_start_inventory !== null
                && $target > 0
                && $record->hour_start_inventory <= $target,
            'efficiency' => $target > 0 && $record->actual > 0
                ? round(($record->actual / $target) * 100, 1)
                : 0,
            'error_rate' => $record->error_rate,
            'status'     => $this->resolveStatus($record),
            'note'              => $record->note,
            'productivity_json' => $record->productivity_json,
            'machine_count'     => $effectiveMachineCount,
            'productivity_type' => $record->department?->productivity_type?->value,
            'active_machines'   => $activeMachines,
            'is_machine_overridden' => $isPerMachineDtg && isset($hourlyMachines) && $hourlyMachines->isNotEmpty(),

            // ── Last manual change ──
            'last_change' => $this->formatLastChange($record),
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
     * Resolve status with real-time accuracy.
     *
     * For today's shift: compute status from hour_slot + now() to avoid
     * stale DB values caused by cache TTL or aggregate pipeline delay.
     * For past shifts: force 'completed'.
     */
    private function resolveStatus(HourlyRecord $record): string
    {
        $now = $this->now ??= now();

        // Past date → all completed
        if ($this->shiftDate && $this->shiftDate->lt(today())) {
            return HourlyRecordStatus::Completed->value;
        }

        // Same day but shift ended → all completed
        if ($this->shiftEndAt && $now->gte($this->shiftEndAt)) {
            return HourlyRecordStatus::Completed->value;
        }

        // Today's active shift: compute from hour_slot in real-time
        if ($this->shiftDate && $this->shiftDate->eq(today()) && $record->hour_slot) {
            return $this->computeRealtimeStatus($record->hour_slot, $now);
        }

        return $record->status;
    }

    /**
     * Compute status from hour_slot string (e.g. "9h-10h") and current time.
     * Uses integer hour comparison to avoid Carbon object creation per record.
     */
    private function computeRealtimeStatus(string $hourSlot, CarbonImmutable|Carbon $now): string
    {
        $parts = explode('-', str_replace('h', '', $hourSlot));
        $startHour = (int) ($parts[0] ?? 0);
        $endHour   = (int) ($parts[1] ?? 0);

        $currentHour = (int) $now->format('G'); // 0-23, no leading zero

        if ($currentHour >= $endHour) {
            return HourlyRecordStatus::Completed->value;
        }

        if ($currentHour >= $startHour) {
            return HourlyRecordStatus::Active->value;
        }

        return HourlyRecordStatus::Pending->value;
    }

    private function formatLastChange(HourlyRecord $record): ?array
    {
        $change = $record->relationLoaded('latestChange')
            ? $record->latestChange
            : null;

        if (!$change) {
            return null;
        }

        return [
            'user_name'  => $change->user_name,
            'changes'    => $change->changes,
            'created_at' => $change->created_at->toIso8601String(),
        ];
    }
}
