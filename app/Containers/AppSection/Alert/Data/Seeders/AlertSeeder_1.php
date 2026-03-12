<?php

namespace App\Containers\AppSection\Alert\Data\Seeders;

use App\Containers\AppSection\Alert\Models\Alert;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds all alerts matching FE data.ts ALERTS constant.
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Alert\Data\Seeders\AlertSeeder_1"
 */
final class AlertSeeder_1 extends Seeder
{
    public function run(): void
    {
        Alert::query()->delete();

        $alerts = [
            [
                'severity' => 'critical',
                'department' => 'Print',
                'time' => '10:42',
                'message' => 'Máy in DTF-03 (DTF1) ngừng hoạt động — cần bảo trì',
                'line' => 'dtf1',
            ],
            [
                'severity' => 'warning',
                'department' => 'Pack & Ship',
                'time' => '10:15',
                'message' => 'Máy dán nhãn LBL-02 (DTF1) đang bảo trì — dự kiến 30 phút',
                'line' => 'dtf1',
            ],
            [
                'severity' => 'warning',
                'department' => 'Mock Up',
                'time' => '09:45',
                'message' => 'Máy SEW-03 (DTF2) ngừng — cần thay kim',
                'line' => 'dtf2',
            ],
            [
                'severity' => 'warning',
                'department' => 'Print',
                'time' => '08:50',
                'message' => 'Mực trắng DTG Apollo còn 15% — đã đặt hàng bổ sung',
                'line' => 'dtg',
            ],
            [
                'severity' => 'info',
                'department' => 'Pack & Ship',
                'time' => '11:05',
                'message' => 'Nhãn vận chuyển sắp hết — còn 200 cái',
                'line' => 'all',
            ],
        ];

        foreach ($alerts as $alert) {
            Alert::create($alert);
        }
    }
}
