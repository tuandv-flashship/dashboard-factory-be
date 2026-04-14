<?php

namespace App\Containers\AppSection\FplatformData\Enums;

/**
 * Types of hourly metrics available from FPlatform.
 *
 * Used to select the correct query variant for hourly-metrics endpoint.
 */
enum HourlyMetricType: string
{
    case Productivity      = 'productivity';       // Sản lượng theo giờ
    case StaffCount        = 'staff_count';         // Số nhân viên theo giờ
    case StaffProductivity = 'staff_productivity';  // Sản lượng từng NV theo giờ
    case MachineProductivity = 'machine_productivity'; // Sản lượng từng máy theo giờ

    public function label(): string
    {
        return match ($this) {
            self::Productivity       => 'Hiệu suất sản xuất',
            self::StaffCount         => 'Số nhân viên',
            self::StaffProductivity  => 'Hiệu suất nhân viên',
            self::MachineProductivity => 'Hiệu suất máy in',
        };
    }
}
