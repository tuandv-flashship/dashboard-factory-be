<?php

namespace App\Containers\AppSection\Shift\Data\Seeders;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds default shift data (today, shift 1).
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Shift\Data\Seeders\ShiftSeeder_1"
 */
final class ShiftSeeder_1 extends Seeder
{
    public function run(): void
    {
        if (Shift::count() > 0) {
            return;
        }

        Shift::create([
            'date'         => now()->toDateString(),
            'shift_number' => 1,
            'start_time'   => '06:00',
            'end_time'     => '14:00',
            'supervisor'   => 'Nguyễn Văn Minh',
            'is_active'    => true,
        ]);
    }
}
