<?php

namespace App\Containers\AppSection\Machine\Data\Seeders;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds all machines matching FE data.ts MACHINES constant.
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Machine\Data\Seeders\MachineSeeder_1"
 */
final class MachineSeeder_1 extends Seeder
{
    public function run(): void
    {
        Machine::query()->delete();

        $machines = [
            // ═════════════════════════════════════════════════
            // DTF1 — Building 1 (16 machines)
            // ═════════════════════════════════════════════════

            // DTF1 Print (6)
            ['code' => 'dtf1-dtg01', 'name' => 'DTF-01', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1'],
            ['code' => 'dtf1-dtg02', 'name' => 'DTF-02', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1'],
            ['code' => 'dtf1-dtg03', 'name' => 'DTF-03', 'status' => 'offline', 'department' => 'print', 'line' => 'dtf1'],
            ['code' => 'dtf1-dtg04', 'name' => 'DTF-04', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1'],
            ['code' => 'dtf1-hp01', 'name' => 'HP-01', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1'],
            ['code' => 'dtf1-hp02', 'name' => 'HP-02', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1'],

            // DTF1 Cut (3)
            ['code' => 'dtf1-cut01', 'name' => 'CUT-01', 'status' => 'online', 'department' => 'cut', 'line' => 'dtf1'],
            ['code' => 'dtf1-cut02', 'name' => 'CUT-02', 'status' => 'online', 'department' => 'cut', 'line' => 'dtf1'],
            ['code' => 'dtf1-cut03', 'name' => 'CUT-03', 'status' => 'online', 'department' => 'cut', 'line' => 'dtf1'],

            // DTF1 MockUp (4)
            ['code' => 'dtf1-sew01', 'name' => 'SEW-01', 'status' => 'online', 'department' => 'mockup', 'line' => 'dtf1'],
            ['code' => 'dtf1-sew02', 'name' => 'SEW-02', 'status' => 'online', 'department' => 'mockup', 'line' => 'dtf1'],
            ['code' => 'dtf1-sew03', 'name' => 'SEW-03', 'status' => 'online', 'department' => 'mockup', 'line' => 'dtf1'],
            ['code' => 'dtf1-sew04', 'name' => 'SEW-04', 'status' => 'online', 'department' => 'mockup', 'line' => 'dtf1'],

            // DTF1 Pack & Ship (4)
            ['code' => 'dtf1-pkg01', 'name' => 'PKG-01', 'status' => 'online', 'department' => 'pack_ship', 'line' => 'dtf1'],
            ['code' => 'dtf1-pkg02', 'name' => 'PKG-02', 'status' => 'online', 'department' => 'pack_ship', 'line' => 'dtf1'],
            ['code' => 'dtf1-lbl01', 'name' => 'LBL-01', 'status' => 'online', 'department' => 'pack_ship', 'line' => 'dtf1'],
            ['code' => 'dtf1-lbl02', 'name' => 'LBL-02', 'status' => 'maintenance', 'department' => 'pack_ship', 'line' => 'dtf1'],

            // ═════════════════════════════════════════════════
            // DTF2 — Building 2 (12 machines)
            // ═════════════════════════════════════════════════

            // DTF2 Print (4)
            ['code' => 'dtf2-dtf01', 'name' => 'DTF-01', 'status' => 'online', 'department' => 'print', 'line' => 'dtf2'],
            ['code' => 'dtf2-dtf02', 'name' => 'DTF-02', 'status' => 'online', 'department' => 'print', 'line' => 'dtf2'],
            ['code' => 'dtf2-dtf03', 'name' => 'DTF-03', 'status' => 'online', 'department' => 'print', 'line' => 'dtf2'],
            ['code' => 'dtf2-hp01', 'name' => 'HP-01', 'status' => 'online', 'department' => 'print', 'line' => 'dtf2'],

            // DTF2 Cut (2)
            ['code' => 'dtf2-cut01', 'name' => 'CUT-01', 'status' => 'online', 'department' => 'cut', 'line' => 'dtf2'],
            ['code' => 'dtf2-cut02', 'name' => 'CUT-02', 'status' => 'online', 'department' => 'cut', 'line' => 'dtf2'],

            // DTF2 MockUp (3)
            ['code' => 'dtf2-sew01', 'name' => 'SEW-01', 'status' => 'online', 'department' => 'mockup', 'line' => 'dtf2'],
            ['code' => 'dtf2-sew02', 'name' => 'SEW-02', 'status' => 'online', 'department' => 'mockup', 'line' => 'dtf2'],
            ['code' => 'dtf2-sew03', 'name' => 'SEW-03', 'status' => 'maintenance', 'department' => 'mockup', 'line' => 'dtf2'],

            // DTF2 Pack & Ship (3)
            ['code' => 'dtf2-pkg01', 'name' => 'PKG-01', 'status' => 'online', 'department' => 'pack_ship', 'line' => 'dtf2'],
            ['code' => 'dtf2-pkg02', 'name' => 'PKG-02', 'status' => 'online', 'department' => 'pack_ship', 'line' => 'dtf2'],
            ['code' => 'dtf2-lbl01', 'name' => 'LBL-01', 'status' => 'online', 'department' => 'pack_ship', 'line' => 'dtf2'],

            // ═════════════════════════════════════════════════
            // DTG — Direct to Garment (3 machines)
            // ═════════════════════════════════════════════════

            // DTG Print (3)
            ['code' => 'dtg-apollo01', 'name' => 'Apollo', 'status' => 'online', 'department' => 'print', 'line' => 'dtg'],
            ['code' => 'dtg-atlas01', 'name' => 'Atlas-01', 'status' => 'online', 'department' => 'print', 'line' => 'dtg'],
            ['code' => 'dtg-atlas02', 'name' => 'Atlas-02', 'status' => 'online', 'department' => 'print', 'line' => 'dtg'],

            // ═════════════════════════════════════════════════
            // Pick / Scan equipment (from departmentData.ts)
            // ═════════════════════════════════════════════════

            // DTF1 Pick (4)
            ['code' => 'dtf1-scan01', 'name' => 'SCAN-01', 'status' => 'online', 'department' => 'pick', 'line' => 'dtf1'],
            ['code' => 'dtf1-scan02', 'name' => 'SCAN-02', 'status' => 'online', 'department' => 'pick', 'line' => 'dtf1'],
            ['code' => 'dtf1-cart01', 'name' => 'CART-01', 'status' => 'online', 'department' => 'pick', 'line' => 'dtf1'],
            ['code' => 'dtf1-cart02', 'name' => 'CART-02', 'status' => 'online', 'department' => 'pick', 'line' => 'dtf1'],

            // DTF2 Pick (2)
            ['code' => 'dtf2-scan03', 'name' => 'SCAN-03', 'status' => 'online', 'department' => 'pick', 'line' => 'dtf2'],
            ['code' => 'dtf2-cart03', 'name' => 'CART-03', 'status' => 'online', 'department' => 'pick', 'line' => 'dtf2'],

            // DTG Pick (2)
            ['code' => 'dtg-scan04', 'name' => 'SCAN-04', 'status' => 'online', 'department' => 'pick', 'line' => 'dtg'],
            ['code' => 'dtg-cart04', 'name' => 'CART-04', 'status' => 'online', 'department' => 'pick', 'line' => 'dtg'],
        ];

        foreach ($machines as $index => $machine) {
            Machine::create(array_merge($machine, [
                'sort_order' => $index + 1,
            ]));
        }
    }
}
