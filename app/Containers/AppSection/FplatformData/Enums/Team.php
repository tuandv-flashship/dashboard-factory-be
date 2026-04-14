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
    case OrderInventory = 'order_inventory';

    public function label(): string
    {
        return match ($this) {
            self::In             => 'In (DTF)',
            self::Cat            => 'Cắt (DTF)',
            self::Pick           => 'Pick (DTF)',
            self::Mockup         => 'Mockup (DTF)',
            self::PackShip       => 'Pack & Ship (DTF)',
            self::DtgPick        => 'Pick (DTG)',
            self::DtgPrint       => 'In (DTG)',
            self::DtgPrintSplit  => 'In (DTG) - Machine Split',
            self::OrderInventory => 'Tồn đơn hàng',
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

    /**
     * Teams that are valid for hourly metrics queries.
     */
    public static function hourlyTeams(): array
    {
        return [
            self::In,
            self::Cat,
            self::Pick,
            self::Mockup,
            self::PackShip,
            self::DtgPick,
            self::DtgPrint,
        ];
    }

    /**
     * Whether this team supports a given metric type for hourly queries.
     */
    public function supportsMetric(HourlyMetricType $metric): bool
    {
        return match ($metric) {
            HourlyMetricType::Productivity => true, // All teams
            HourlyMetricType::StaffCount => true,   // All teams
            HourlyMetricType::StaffProductivity => match ($this) {
                self::In, self::DtgPrint => false,  // IN doesn't have per-staff
                default => true,
            },
            HourlyMetricType::MachineProductivity => match ($this) {
                self::In, self::DtgPrint => true,   // Only print teams
                default => false,
            },
        };
    }
}
