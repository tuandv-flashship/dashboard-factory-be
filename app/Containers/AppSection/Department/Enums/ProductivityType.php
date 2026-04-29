<?php

namespace App\Containers\AppSection\Department\Enums;

enum ProductivityType: string
{
    case PerPerson     = 'per_person';
    case PerMachineDtg = 'per_machine_dtg';
    case PerMachineDtf = 'per_machine_dtf';

    /**
     * Check if this type uses per-machine logic (either DTG or DTF).
     * Useful for UI display (e.g. show machine-related fields).
     */
    public function isPerMachine(): bool
    {
        return match ($this) {
            self::PerMachineDtg, self::PerMachineDtf => true,
            default => false,
        };
    }

    /**
     * DTG-specific: uses individual machine selection (pivot) and aggregated KPI.
     * Target = Σ(machine.kpi) — no multiplier.
     */
    public function isPerMachineDtg(): bool
    {
        return $this === self::PerMachineDtg;
    }

    /**
     * DTF-specific: uses dept-level KPI × machine_count.
     * No individual machine selection (no pivot).
     */
    public function isPerMachineDtf(): bool
    {
        return $this === self::PerMachineDtf;
    }

    public function label(): string
    {
        return match ($this) {
            self::PerPerson     => 'Theo người',
            self::PerMachineDtg => 'Theo máy DTG',
            self::PerMachineDtf => 'Theo máy DTF',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::PerPerson     => 'Per Person',
            self::PerMachineDtg => 'Per Machine (DTG)',
            self::PerMachineDtf => 'Per Machine (DTF)',
        };
    }

    /** Get all cases as [value => label] for select options. */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case) => $case->label(), self::cases()),
        );
    }
}
