<?php

namespace App\Containers\AppSection\FplatformData\Enums;

enum Team: string
{
    case Print         = 'print';
    case Cut           = 'cut';
    case Pick          = 'pick';
    case Mockup        = 'mockup';
    case PackShip      = 'pack_ship';
    case PickDtg       = 'pick_dtg';
    case DtgPrint      = 'dtg_print';
    case DtgPrintSplit = 'dtg_print_split';
    case OrderInventory = 'order_inventory';
    case HotshotPrint   = 'hotshot_print';
    case HotshotPick    = 'hotshot_pick';
    case HotshotCut     = 'hotshot_cut';
    case HotshotMockup  = 'hotshot_mockup';
    case HotshotPackShip = 'hotshot_pack_ship';

    public function label(): string
    {
        return match ($this) {
            self::Print          => 'In (DTF)',
            self::Cut            => 'Cắt (DTF)',
            self::Pick           => 'Pick (DTF)',
            self::Mockup         => 'Mockup (DTF)',
            self::PackShip       => 'Pack & Ship (DTF)',
            self::PickDtg        => 'Pick (DTG)',
            self::DtgPrint       => 'In (DTG)',
            self::DtgPrintSplit  => 'In (DTG) - Machine Split',
            self::OrderInventory => 'Tồn đơn hàng',
            self::HotshotPrint   => 'Hotshot In',
            self::HotshotPick    => 'Hotshot Pick',
            self::HotshotCut     => 'Hotshot Cắt',
            self::HotshotMockup  => 'Hotshot Mockup',
            self::HotshotPackShip => 'Hotshot Pack & Ship',
        };
    }

    /**
     * Whether this team requires a factory line parameter.
     */
    public function requiresFactory(): bool
    {
        return match ($this) {
            self::PickDtg, self::DtgPrint, self::DtgPrintSplit => false,
            default => true,
        };
    }

    /**
     * Teams that are valid for hourly metrics queries.
     */
    public static function hourlyTeams(): array
    {
        return [
            self::Print,
            self::Cut,
            self::Pick,
            self::Mockup,
            self::PackShip,
            self::PickDtg,
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
                self::Print, self::DtgPrint => false,  // Print teams don't have per-staff
                default => true,
            },
            HourlyMetricType::MachineProductivity => match ($this) {
                self::Print, self::DtgPrint => true,   // Only print teams
                default => false,
            },
        };
    }
}
