<?php

namespace App\Containers\AppSection\Department\Enums;

enum ProductivityType: string
{
    case PerPerson  = 'per_person';
    case PerMachine = 'per_machine';

    public function label(): string
    {
        return match ($this) {
            self::PerPerson  => 'Theo người',
            self::PerMachine => 'Theo máy',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::PerPerson  => 'Per Person',
            self::PerMachine => 'Per Machine',
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
