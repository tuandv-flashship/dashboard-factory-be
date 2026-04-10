<?php

namespace App\Containers\AppSection\FplatformData\Enums;

enum FactoryLine: string
{
    case FLS = 'FLS';
    case PD = 'PD';

    /**
     * Get the FactoryLine for the current deployment.
     * Reads from config('factory.current') which is set via FACTORY env variable.
     */
    public static function current(): self
    {
        return self::from(config('factory.current'));
    }

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
            self::FLS => 'FlashShip',
            self::PD  => 'PrintDash',
        };
    }
}
