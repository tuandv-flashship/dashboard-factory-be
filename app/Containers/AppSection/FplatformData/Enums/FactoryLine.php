<?php

namespace App\Containers\AppSection\FplatformData\Enums;

enum FactoryLine: string
{
    case FLS = 'FLS';
    case PD = 'PD';

    /**
     * Get additional hotshot/reprint printer names for this factory line.
     */
    public function extraPrinters(): array
    {
        return match ($this) {
            self::FLS => ['MayHOTSHOT', 'MayREPRINT'],
            self::PD  => ['MayHOTSHOTPD', 'MayREPRINTPD'],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::FLS => 'DTF1 - FlashShip',
            self::PD  => 'DTF2 - PrintDash',
        };
    }
}
