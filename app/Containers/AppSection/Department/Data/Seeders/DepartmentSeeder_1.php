<?php

namespace App\Containers\AppSection\Department\Data\Seeders;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds department data for all production lines.
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Department\Data\Seeders\DepartmentSeeder_1"
 */
final class DepartmentSeeder_1 extends Seeder
{
    public function run(): void
    {
        if (Department::count() > 0) {
            return;
        }

        $dtf1 = ProductionLine::where('code', 'dtf1')->first();
        $dtf2 = ProductionLine::where('code', 'dtf2')->first();
        $dtg  = ProductionLine::where('code', 'dtg')->first();
        $pick = ProductionLine::where('code', 'pick')->first();

        if (!$dtf1 || !$dtf2 || !$dtg || !$pick) {
            $this->command?->warn('Production lines not found. Run ProductionLineSeeder first.');
            return;
        }

        $now = now();

        Department::insert([
            // DTF1 (4)
            ['production_line_id' => $dtf1->id, 'code' => 'print', 'label' => 'In ấn', 'label_en' => 'Print', 'icon' => 'Printer', 'unit' => 'file', 'kpi_per_hour' => 130, 'factory' => 'FLS', 'sort_order' => 1, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf1->id, 'code' => 'cut', 'label' => 'Cắt', 'label_en' => 'Cut', 'icon' => 'Scissors', 'unit' => 'file', 'kpi_per_hour' => 280, 'factory' => 'FLS', 'sort_order' => 2, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf1->id, 'code' => 'mockup', 'label' => 'Ráp mẫu', 'label_en' => 'Mock Up', 'icon' => 'Layers', 'unit' => 'file', 'kpi_per_hour' => 75, 'factory' => 'FLS', 'sort_order' => 3, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf1->id, 'code' => 'pack_ship', 'label' => 'Đóng gói & Giao', 'label_en' => 'Pack & Ship', 'icon' => 'Package', 'unit' => 'shirt', 'kpi_per_hour' => 105, 'factory' => 'FLS', 'sort_order' => 4, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            // DTF2 (4)
            ['production_line_id' => $dtf2->id, 'code' => 'print', 'label' => 'In ấn', 'label_en' => 'Print', 'icon' => 'Printer', 'unit' => 'file', 'kpi_per_hour' => 130, 'factory' => 'PD', 'sort_order' => 1, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf2->id, 'code' => 'cut', 'label' => 'Cắt', 'label_en' => 'Cut', 'icon' => 'Scissors', 'unit' => 'file', 'kpi_per_hour' => 280, 'factory' => 'PD', 'sort_order' => 2, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf2->id, 'code' => 'mockup', 'label' => 'Ráp mẫu', 'label_en' => 'Mock Up', 'icon' => 'Layers', 'unit' => 'file', 'kpi_per_hour' => 75, 'factory' => 'PD', 'sort_order' => 3, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf2->id, 'code' => 'pack_ship', 'label' => 'Đóng gói & Giao', 'label_en' => 'Pack & Ship', 'icon' => 'Package', 'unit' => 'shirt', 'kpi_per_hour' => 105, 'factory' => 'PD', 'sort_order' => 4, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            // DTG (3)
            ['production_line_id' => $dtg->id, 'code' => 'apollo', 'label' => 'Apollo', 'label_en' => 'Apollo', 'icon' => 'Printer', 'unit' => 'print', 'kpi_per_hour' => 400, 'factory' => 'PD', 'sort_order' => 1, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtg->id, 'code' => 'atlas_01', 'label' => 'Atlas-01', 'label_en' => 'Atlas-01', 'icon' => 'Printer', 'unit' => 'print', 'kpi_per_hour' => 400, 'factory' => 'PD', 'sort_order' => 2, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtg->id, 'code' => 'atlas_02', 'label' => 'Atlas-02', 'label_en' => 'Atlas-02', 'icon' => 'Printer', 'unit' => 'print', 'kpi_per_hour' => 400, 'factory' => 'PD', 'sort_order' => 3, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            // Pick (3)
            ['production_line_id' => $pick->id, 'code' => 'dtf1', 'label' => 'Pick DTF 1', 'label_en' => 'Pick DTF 1', 'icon' => 'ShoppingCart', 'unit' => 'shirt', 'kpi_per_hour' => 180, 'factory' => 'FLS', 'sort_order' => 1, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $pick->id, 'code' => 'dtf2', 'label' => 'Pick DTF 2', 'label_en' => 'Pick DTF 2', 'icon' => 'ShoppingCart', 'unit' => 'shirt', 'kpi_per_hour' => 180, 'factory' => 'PD', 'sort_order' => 2, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $pick->id, 'code' => 'dtg', 'label' => 'Pick DTG', 'label_en' => 'Pick DTG', 'icon' => 'ShoppingCart', 'unit' => 'shirt', 'kpi_per_hour' => 180, 'factory' => 'PD', 'sort_order' => 3, 'is_active' => true, 'can_increase_productivity' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
