<?php

namespace App\Containers\AppSection\Quality\Data\Seeders;

use App\Containers\AppSection\Quality\Models\QualityRecord;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds quality data matching FE data.ts QUALITY_DATA.
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Quality\Data\Seeders\QualitySeeder_1"
 */
final class QualitySeeder_1 extends Seeder
{
    public function run(): void
    {
        QualityRecord::query()->delete();

        QualityRecord::create([
            'date' => now()->toDateString(),
            'shift_number' => 1,
            'pass_rate' => 98.1,
            'inspected' => 1056,
            'passed' => 1036,
            'failed' => 20,
            'avg_error_rate' => 1.9,
        ]);
    }
}
