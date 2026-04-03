<?php

namespace App\Containers\AppSection\FplatformData\Enums;

enum Team: string
{
    case In            = 'in';
    case Cat           = 'cat';
    case Pick          = 'pick';
    case Mockup        = 'mockup';
    case PackShip      = 'pack_ship';
    case DtgPick       = 'dtg_pick';
    case DtgPrint      = 'dtg_print';
    case DtgPrintSplit = 'dtg_print_split';

    public function label(): string
    {
        return match ($this) {
            self::In            => 'In (DTF)',
            self::Cat           => 'Cắt (DTF)',
            self::Pick          => 'Pick (DTF)',
            self::Mockup        => 'Mockup (DTF)',
            self::PackShip      => 'Pack & Ship (DTF)',
            self::DtgPick       => 'Pick (DTG)',
            self::DtgPrint      => 'In (DTG)',
            self::DtgPrintSplit => 'In (DTG) - Machine Split',
        };
    }

    /**
     * Whether this team requires a factory line parameter.
     */
    public function requiresFactory(): bool
    {
        return match ($this) {
            self::DtgPick, self::DtgPrint, self::DtgPrintSplit => false,
            default => true,
        };
    }
}
