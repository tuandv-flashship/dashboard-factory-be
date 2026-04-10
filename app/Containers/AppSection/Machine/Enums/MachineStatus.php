<?php

namespace App\Containers\AppSection\Machine\Enums;

enum MachineStatus: string
{
    case Online      = 'online';
    case Offline     = 'offline';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Online      => 'Hoạt động',
            self::Offline     => 'Ngừng',
            self::Maintenance => 'Bảo trì',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::Online      => 'Online',
            self::Offline     => 'Offline',
            self::Maintenance => 'Maintenance',
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
