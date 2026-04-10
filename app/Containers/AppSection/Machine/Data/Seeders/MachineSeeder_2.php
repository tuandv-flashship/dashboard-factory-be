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
 * PD: Only DTG Print department has machines (Apollo, Atlas-01, Atlas-02)
 *     with per-machine KPI tracking.
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
     * PD — DTG Print machines (3 machines)
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
        $machines = [
            ['department_id' => $dtgPrint->id, 'code' => 'apollo',   'name' => 'Apollo',   'description' => 'Máy in DTG Apollo — 250 mặt in/giờ',   'status' => 'online', 'unit' => 'print', 'kpi_per_hour' => 250, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['department_id' => $dtgPrint->id, 'code' => 'atlas_01', 'name' => 'Atlas-01', 'description' => 'Máy in DTG Atlas-01 — 75 mặt in/giờ',  'status' => 'online', 'unit' => 'print', 'kpi_per_hour' => 75,  'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['department_id' => $dtgPrint->id, 'code' => 'atlas_02', 'name' => 'Atlas-02', 'description' => 'Máy in DTG Atlas-02 — 75 mặt in/giờ',  'status' => 'online', 'unit' => 'print', 'kpi_per_hour' => 75,  'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ];

        Machine::insert($machines);
    }
}
