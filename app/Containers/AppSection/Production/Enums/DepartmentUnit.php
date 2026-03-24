<?php

namespace App\Containers\AppSection\Production\Enums;

enum DepartmentUnit: string
{
    case File  = 'file';
    case Shirt = 'shirt';
    case Print = 'print';

    public function label(): string
    {
        return match ($this) {
            self::File  => 'File',
            self::Shirt => 'Shirt',
            self::Print => 'Print',
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
