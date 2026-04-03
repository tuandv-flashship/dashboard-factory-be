<?php

namespace App\Containers\AppSection\FplatformData\Enums;

enum WorkType: int
{
    case In   = 0;   // Printing
    case Cat  = 2;   // Cutting
    case Pick = 100; // Picking

    public function label(): string
    {
        return match ($this) {
            self::In   => 'In',
            self::Cat  => 'Cắt',
            self::Pick => 'Pick',
        };
    }
}
