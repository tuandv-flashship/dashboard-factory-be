<?php

namespace App\Containers\AppSection\Order\Data\Seeders;

use App\Containers\AppSection\Order\Models\OrderSummary;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds order summaries matching FE data.ts TOTAL_ORDERS + LINE_ORDERS.
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Order\Data\Seeders\OrderSeeder_1"
 */
final class OrderSeeder_1 extends Seeder
{
    public function run(): void
    {
        OrderSummary::query()->delete();

        $today = now()->toDateString();

        // TOTAL_ORDERS (line = null)
        OrderSummary::create([
            'date' => $today,
            'shift_number' => 1,
            'line' => null,
            'line_label' => null,
            'total' => 1850,
            'completed' => 1056,
            'remaining' => 794,
            'estimated_done' => '16:30',
            'rush_completed' => 42,
            'rush_total' => 58,
            'progress' => 57,
        ]);

        // LINE_ORDERS
        $lineOrders = [
            ['dtf1', 'DTF 1', 748, 423, 325, '15:45', 18, 24, 57],
            ['dtf2', 'DTF 2', 620, 362, 258, '16:00', 14, 20, 58],
            ['dtg', 'DTG', 482, 271, 211, '16:30', 10, 14, 56],
        ];

        foreach ($lineOrders as [$line, $label, $total, $completed, $remaining, $est, $rushComp, $rushTotal, $progress]) {
            OrderSummary::create([
                'date' => $today,
                'shift_number' => 1,
                'line' => $line,
                'line_label' => $label,
                'total' => $total,
                'completed' => $completed,
                'remaining' => $remaining,
                'estimated_done' => $est,
                'rush_completed' => $rushComp,
                'rush_total' => $rushTotal,
                'progress' => $progress,
            ]);
        }
    }
}
