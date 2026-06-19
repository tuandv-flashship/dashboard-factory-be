<?php

namespace App\Containers\AppSection\Production\Data\Seeders;

use App\Containers\AppSection\Department\Data\Seeders\DepartmentSeeder_2;
use App\Containers\AppSection\Machine\Data\Seeders\MachineSeeder_2;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds production data: lines, then delegates to DepartmentSeeder,
 * then seeds hourly records and issues.
 *
 * Uses config('factory.current') to determine which factory's data to seed.
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Production\Data\Seeders\ProductionSeeder_1"
 */
final class ProductionSeeder_1 extends Seeder
{
    private const SHIFT_HOURS = ['6h-7h', '7h-8h', '8h-9h', '9h-10h', '10h-11h', '11h-12h', '12h-13h', '13h-14h'];

    public function run(): void
    {
        if (ProductionLine::count() > 0) {
            return;
        }

        $factory = config('factory.current');
        $now = now();

        // ═══════════════════════════════════════════════════════
        // 1. PRODUCTION LINES (factory-specific)
        // ═══════════════════════════════════════════════════════
        $lines = match ($factory) {
            'FLS' => [
                ['code' => 'dtf', 'label' => 'DTF', 'color' => '#f59e0b', 'subtitle' => 'Building 1', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ],
            'PD' => [
                ['code' => 'pick',      'label' => 'Pick',        'color' => '#06b6d4', 'subtitle' => 'Lấy hàng — Chung',  'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'dtf',       'label' => 'DTF',         'color' => '#14b8a6', 'subtitle' => 'Building 2',        'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'dtg',       'label' => 'DTG',         'color' => '#8b5cf6', 'subtitle' => 'Apollo + 2× Atlas', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'pack_ship', 'label' => 'Pack & Ship', 'color' => '#ec4899', 'subtitle' => 'Đóng gói & Giao',  'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ],
        };

        ProductionLine::insert($lines);

        // ═══════════════════════════════════════════════════════
        // 2. DEPARTMENTS — delegated to Department container
        // ═══════════════════════════════════════════════════════
        $this->call(DepartmentSeeder_2::class);
        $this->call(MachineSeeder_2::class);

        // Re-fetch for hourly record seeding
        $departments = Department::with('productionLine')->get()
            ->keyBy(fn ($d) => "{$d->productionLine->code}-{$d->code}");

        // ═══════════════════════════════════════════════════════
        // 3. SHIFT — from Shift container seeder (ShiftSeeder_1)
        // ═══════════════════════════════════════════════════════
        $shift = Shift::current();
        if (!$shift) {
            $this->command?->warn('No shift found. Run ShiftSeeder_1 first.');
            return;
        }

        // ═══════════════════════════════════════════════════════
        // 4. HOURLY RECORDS — batch insert (factory-specific)
        // ═══════════════════════════════════════════════════════

        // [dept_key, staff, efficiency, errorRate, [target, actual] x 8]
        $hourlyData = match ($factory) {
            'FLS' => [
                // DTF — 5 departments
                ['dtf-print',     12, 94.2, 2.1, [[94,95],[90,94],[97,95],[92,87],[94,82],[97,49],[97,null],[93,null]]],
                ['dtf-pick',       3, 92.0, 1.2, [[160,155],[160,162],[165,168],[165,158],[160,150],[160,80],[165,null],[165,null]]],
                ['dtf-cut',        8, 91.5, 1.4, [[97,92],[99,99],[102,105],[102,98],[98,89],[102,49],[96,null],[97,null]]],
                ['dtf-mockup',    10, 96.8, 0.8, [[84,92],[93,100],[91,92],[92,91],[86,81],[94,48],[85,null],[90,null]]],
                ['dtf-pack_ship', 14, 87.3, 3.2, [[83,73],[81,74],[87,83],[86,86],[85,79],[83,41],[89,null],[82,null]]],
            ],
            'PD' => [
                // DTF — 3 departments (pick moved to Pick line)
                ['dtf-print',      10, 92.1, 1.8, [[88,90],[85,88],[90,87],[87,85],[89,80],[91,45],[88,null],[86,null]]],
                ['dtf-cut',         6, 93.7, 1.1, [[82,85],[84,86],[86,88],[85,82],[83,78],[87,43],[84,null],[81,null]]],
                ['dtf-mockup',      8, 95.2, 1.0, [[75,78],[78,80],[76,77],[79,76],[74,70],[80,40],[77,null],[75,null]]],
                // DTG — 1 department
                ['dtg-dtg_print',   2, 88.4, 2.8, [[400,385],[400,392],[400,410],[400,378],[400,365],[400,195],[400,null],[400,null]]],
                // Pick — 3 children (parent is aggregated from these)
                ['pick-pick_dtf',   3, 90.5, 1.8, [[135,130],[135,132],[140,142],[140,135],[135,128],[135,68],[140,null],[140,null]]],
                ['pick-pick_dtg',   2, 91.0, 1.5, [[105,95],[105,101],[95,100],[95,95],[105,92],[105,47],[95,null],[95,null]]],
                ['pick-pick_dtf2',  2, 90.0, 1.0, [[50,45],[50,48],[50,52],[50,50],[50,47],[50,23],[50,null],[50,null]]],
                // Pick parent — aggregated from pick_dtf + pick_dtg + pick_dtf2
                ['pick-pick',       7, 90.5, 1.5, [[290,270],[290,281],[285,294],[285,280],[290,267],[290,138],[285,null],[285,null]]],
                // Pack & Ship
                ['pack_ship-pack_ship', 10, 89.5, 2.5, [[70,65],[72,68],[74,72],[73,71],[71,66],[75,37],[72,null],[70,null]]],
            ],
        };

        $hourlyRecords = [];
        foreach ($hourlyData as [$deptKey, $staff, $efficiency, $errorRate, $hours]) {
            $dept = $departments->get($deptKey);
            if (!$dept) {
                $this->command?->warn("Department '{$deptKey}' not found, skipping hourly records.");
                continue;
            }
            foreach ($hours as $i => [$target, $actual]) {
                $hourlyRecords[] = [
                    'shift_id' => $shift->id,
                    'department_id' => $dept->id,
                    'hour_slot' => self::SHIFT_HOURS[$i],
                    'hour_index' => $i,
                    'target' => $target,
                    'actual' => $actual,
                    'staff' => $staff,
                    'efficiency' => $efficiency,
                    'error_rate' => $errorRate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Single batch insert
        HourlyRecord::insert($hourlyRecords);

        // ═══════════════════════════════════════════════════════
        // 5. HOURLY ISSUES — batch insert for missed hours
        // ═══════════════════════════════════════════════════════

        $allRecords = HourlyRecord::where('shift_id', $shift->id)->get();
        $issueRecords = [];

        foreach ($allRecords as $record) {
            if ($record->actual === null || $record->actual >= $record->target) {
                continue;
            }

            $gap = $record->target - $record->actual;

            if ($gap > $record->target * 0.1) {
                $issueRecords[] = [
                    'hourly_record_id' => $record->id,
                    'category' => 'machine',
                    'sub_item' => 'Máy chính',
                    'error' => 'Chạy chậm / Giảm tốc độ',
                    'note' => "Giảm {$gap} so với KPI",
                    'resolution' => $gap > $record->target * 0.2 ? null : 'Đã khắc phục, tăng tốc giờ sau',
                    'resolved_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($gap > $record->target * 0.05 && $gap <= $record->target * 0.1) {
                $issueRecords[] = [
                    'hourly_record_id' => $record->id,
                    'category' => 'human',
                    'sub_item' => 'Nhân viên mới / Chưa thạo',
                    'error' => 'Chưa được đào tạo',
                    'note' => '1 nhân viên mới, cần hỗ trợ',
                    'resolution' => null,
                    'resolved_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($issueRecords)) {
            HourlyIssue::insert($issueRecords);
        }
    }
}
