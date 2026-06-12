<?php

namespace App\Containers\AppSection\Department\Data\Seeders;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds department data for the current factory's production lines.
 *
 * Uses config('factory.current') to determine which departments to seed.
 *
 * FLS departments: DTF (Print, Pick, Cut, Mockup, Pack & Ship) — all per_person
 * PD  departments: DTF (Print, Pick, Cut, Mockup) + DTG (Pick DTG, DTG Print) + Pack & Ship
 *                  DTG Print is per_machine (machines seeded separately in MachineSeeder)
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Department\Data\Seeders\DepartmentSeeder_2"
 */
final class DepartmentSeeder_2 extends Seeder
{
    public function run(): void
    {
        if (Department::count() > 0) {
            return;
        }

        $factory = config('factory.current');
        $now = now();

        $dtf = ProductionLine::where('code', 'dtf')->first();
        if (!$dtf) {
            return; // ProductionSeeder will call us after creating production lines
        }

        $departments = match ($factory) {
            'FLS' => [
                // DTF — 5 departments (all per_person)
                ['production_line_id' => $dtf->id, 'code' => 'print',     'label' => 'In ấn',           'label_en' => 'Print',       'icon' => 'Printer',      'unit' => 'file',  'kpi_per_hour' => 130, 'sort_order' => 1, 'is_active' => true, 'productivity_type' => 'per_person', 'created_at' => $now, 'updated_at' => $now],
                ['production_line_id' => $dtf->id, 'code' => 'pick',      'label' => 'Pick',            'label_en' => 'Pick',        'icon' => 'ShoppingCart', 'unit' => 'shirt', 'kpi_per_hour' => 180, 'sort_order' => 2, 'is_active' => true, 'productivity_type' => 'per_person', 'created_at' => $now, 'updated_at' => $now],
                ['production_line_id' => $dtf->id, 'code' => 'cut',       'label' => 'Cắt',             'label_en' => 'Cut',         'icon' => 'Scissors',     'unit' => 'file',  'kpi_per_hour' => 280, 'sort_order' => 3, 'is_active' => true, 'productivity_type' => 'per_person', 'created_at' => $now, 'updated_at' => $now],
                ['production_line_id' => $dtf->id, 'code' => 'mockup',    'label' => 'Ráp mẫu',         'label_en' => 'Mock Up',     'icon' => 'Layers',       'unit' => 'file',  'kpi_per_hour' => 75,  'sort_order' => 4, 'is_active' => true, 'productivity_type' => 'per_person', 'created_at' => $now, 'updated_at' => $now],
                ['production_line_id' => $dtf->id, 'code' => 'pack_ship', 'label' => 'Đóng gói & Giao', 'label_en' => 'Pack & Ship', 'icon' => 'Package',      'unit' => 'shirt', 'kpi_per_hour' => 105, 'sort_order' => 5, 'is_active' => true, 'productivity_type' => 'per_person', 'created_at' => $now, 'updated_at' => $now],
            ],
            'PD' => $this->buildPdDepartments($dtf, $now),
        };

        if (!empty($departments)) {
            Department::insert($departments);
        }
    }

    /**
     * Build PD departments: DTF (3) + DTG (1) + Pick (1 parent + 2 children) + Pack & Ship (1).
     *
     * Pick parent is visible on dashboard; Pick DTF + Pick DTG are hidden children
     * that sync from FPlatform independently, with data aggregated into the parent.
     */
    private function buildPdDepartments(ProductionLine $dtf, \DateTimeInterface $now): array
    {
        $dtg      = ProductionLine::where('code', 'dtg')->firstOrFail();
        $packShip = ProductionLine::where('code', 'pack_ship')->firstOrFail();
        $pick     = ProductionLine::where('code', 'pick')->first();

        $departments = [
            // DTF — 3 departments (pick moved to Pick line)
            ['production_line_id' => $dtf->id, 'code' => 'print',  'label' => 'In ấn',   'label_en' => 'Print',   'icon' => 'Printer',      'unit' => 'file',  'kpi_per_hour' => 130, 'sort_order' => 1, 'is_active' => true, 'is_hidden' => false, 'productivity_type' => 'per_person', 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf->id, 'code' => 'cut',    'label' => 'Cắt',     'label_en' => 'Cut',     'icon' => 'Scissors',     'unit' => 'file',  'kpi_per_hour' => 280, 'sort_order' => 2, 'is_active' => true, 'is_hidden' => false, 'productivity_type' => 'per_person', 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf->id, 'code' => 'mockup', 'label' => 'Ráp mẫu', 'label_en' => 'Mock Up', 'icon' => 'Layers',       'unit' => 'file',  'kpi_per_hour' => 75,  'sort_order' => 3, 'is_active' => true, 'is_hidden' => false, 'productivity_type' => 'per_person', 'created_at' => $now, 'updated_at' => $now],
            // DTG — 1 department (pick_dtg moved to Pick line)
            ['production_line_id' => $dtg->id, 'code' => 'dtg_print', 'label' => 'DTG Print', 'label_en' => 'DTG Print', 'icon' => 'Printer', 'unit' => 'print', 'kpi_per_hour' => 0, 'sort_order' => 1, 'is_active' => true, 'is_hidden' => false, 'productivity_type' => 'per_machine_dtg', 'created_at' => $now, 'updated_at' => $now],
            // Pack & Ship — 1 department
            ['production_line_id' => $packShip->id, 'code' => 'pack_ship', 'label' => 'Đóng gói & Giao', 'label_en' => 'Pack & Ship', 'icon' => 'Package', 'unit' => 'shirt', 'kpi_per_hour' => 105, 'sort_order' => 1, 'is_active' => true, 'is_hidden' => false, 'productivity_type' => 'per_person', 'created_at' => $now, 'updated_at' => $now],
        ];

        // Insert non-Pick departments first
        Department::insert($departments);

        // Pick line: create parent first, then children referencing parent_id
        if ($pick) {
            $pickParent = Department::create([
                'production_line_id' => $pick->id,
                'parent_id'          => null,
                'code'               => 'pick',
                'label'              => 'Pick',
                'label_en'           => 'Pick',
                'icon'               => 'ShoppingCart',
                'unit'               => 'shirt',
                'kpi_per_hour'       => 0,
                'factory'            => 'PD',
                'sort_order'         => 1,
                'is_active'          => true,
                'is_hidden'          => false,
                'productivity_type'  => 'per_person',
            ]);

            Department::insert([
                ['production_line_id' => $pick->id, 'parent_id' => $pickParent->id, 'code' => 'pick_dtf', 'label' => 'Pick DTF', 'label_en' => 'Pick DTF', 'icon' => 'ShoppingCart', 'unit' => 'shirt', 'kpi_per_hour' => 180, 'sort_order' => 2, 'is_active' => true, 'is_hidden' => true, 'productivity_type' => 'per_person', 'created_at' => $now, 'updated_at' => $now],
                ['production_line_id' => $pick->id, 'parent_id' => $pickParent->id, 'code' => 'pick_dtg', 'label' => 'Pick DTG', 'label_en' => 'Pick DTG', 'icon' => 'ShoppingCart', 'unit' => 'shirt', 'kpi_per_hour' => 180, 'sort_order' => 3, 'is_active' => true, 'is_hidden' => true, 'productivity_type' => 'per_person', 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        return []; // Already inserted above
    }
}
