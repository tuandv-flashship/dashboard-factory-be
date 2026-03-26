<?php

namespace App\Containers\AppSection\Department\Enums;

enum Factory: string
{
    case FLS = 'FLS';
    case PD  = 'PD';

    public function label(): string
    {
        return match ($this) {
            self::FLS => 'FLS',
            self::PD  => 'PD',
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
