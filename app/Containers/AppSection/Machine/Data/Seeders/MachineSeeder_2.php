<?php

namespace App\Containers\AppSection\Machine\Data\Seeders;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds machines for the current factory deployment.
 *
 * Uses config('factory.current') to determine which machines to seed.
 * Machines are linked to departments via department_id FK.
 *
 * PD: Only DTG Print department has machines (6 DTG printers) with per-machine
 *     KPI tracking. Machine `code` MUST equal strtolower(fplatform printed_by):
 *     Apollo, ATLAS_1, ATLAS_2, ATLAS4, ATLAS5, Apollo6.
 * FLS: No machines seeded (all departments are per_person).
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Machine\Data\Seeders\MachineSeeder_2"
 */
final class MachineSeeder_2 extends Seeder
{
    public function run(): void
    {
        if (Machine::count() > 0) {
            return;
        }

        $factory = config('factory.current');

        match ($factory) {
            'FLS' => null, // No machines for FLS
            'PD'  => $this->seedPdMachines(),
        };
    }

    /**
     * PD — DTG Print machines (6 machines; display order Apollo 1 … Apollo 6)
     */
    private function seedPdMachines(): void
    {
        // Fetch DTG Print department
        $dtgPrint = Department::whereHas('productionLine', fn ($q) => $q->where('code', 'dtg'))
            ->where('code', 'dtg_print')
            ->first();

        if (!$dtgPrint) {
            return; // ProductionSeeder will call us after DepartmentSeeder
        }

        $now = now();
        $base = ['department_id' => $dtgPrint->id, 'status' => 'online', 'unit' => 'print', 'created_at' => $now, 'updated_at' => $now];
        $machines = [
            ['code' => 'apollo',  'name' => 'Apollo 1', 'kpi_per_hour' => 280, 'sort_order' => 1],
            ['code' => 'atlas_1', 'name' => 'Atlas 2',  'kpi_per_hour' => 90,  'sort_order' => 2],
            ['code' => 'atlas_2', 'name' => 'Atlas 3',  'kpi_per_hour' => 98,  'sort_order' => 3],
            ['code' => 'atlas4',  'name' => 'Matrix 4', 'kpi_per_hour' => 90,  'sort_order' => 4],
            ['code' => 'atlas5',  'name' => 'Matrix 5', 'kpi_per_hour' => 90,  'sort_order' => 5],
            ['code' => 'apollo6', 'name' => 'Apollo 6', 'kpi_per_hour' => 280, 'sort_order' => 6],
        ];
        $machines = array_map(
            fn (array $m) => $m + $base + ['description' => "Máy in DTG {$m['name']} — {$m['kpi_per_hour']} mặt in/giờ"],
            $machines,
        );

        Machine::insert($machines);
    }
}
