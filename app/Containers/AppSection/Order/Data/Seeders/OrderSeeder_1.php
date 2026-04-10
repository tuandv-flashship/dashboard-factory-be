<?php

namespace App\Containers\AppSection\Order\Data\Seeders;

use App\Containers\AppSection\Order\Models\OrderSummary;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds order summaries matching FE data.ts TOTAL_ORDERS + LINE_ORDERS.
 *
 * Uses config('factory.current') to determine which lines to seed.
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Order\Data\Seeders\OrderSeeder_1"
 */
final class OrderSeeder_1 extends Seeder
{
    public function run(): void
    {
        if (OrderSummary::count() > 0) {
            return;
        }

        $factory = config('factory.current');
        $today = now()->toDateString();

        // LINE_ORDERS (factory-specific)
        $lineOrders = match ($factory) {
            'FLS' => [
                // FLS only has DTF
                ['dtf', 'DTF', 1850, 1056, 794, '16:30', 42, 58, 57],
            ],
            'PD' => [
                // PD has DTF, DTG, Pack & Ship
                ['dtf',       'DTF',         748, 423, 325, '15:45', 18, 24, 57],
                ['dtg',       'DTG',         620, 362, 258, '16:00', 14, 20, 58],
                ['pack_ship', 'Pack & Ship', 482, 271, 211, '16:30', 10, 14, 56],
            ],
        };

        // Compute total from line data
        $totalOrders = 0;
        $totalCompleted = 0;
        $totalRemaining = 0;
        $totalRushComp = 0;
        $totalRushTotal = 0;

        foreach ($lineOrders as [, , $total, $completed, $remaining, , $rushComp, $rushTotal, ]) {
            $totalOrders += $total;
            $totalCompleted += $completed;
            $totalRemaining += $remaining;
            $totalRushComp += $rushComp;
            $totalRushTotal += $rushTotal;
        }

        $totalProgress = $totalOrders > 0 ? round(($totalCompleted / $totalOrders) * 100) : 0;

        // TOTAL_ORDERS (line = null)
        OrderSummary::create([
            'date' => $today,
            'shift_number' => 1,
            'line' => null,
            'line_label' => null,
            'total' => $totalOrders,
            'completed' => $totalCompleted,
            'remaining' => $totalRemaining,
            'estimated_done' => '16:30',
            'rush_completed' => $totalRushComp,
            'rush_total' => $totalRushTotal,
            'progress' => $totalProgress,
        ]);

        // Per-line orders
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
