<?php

namespace App\Containers\AppSection\Production\Data\Seeders;

use App\Containers\AppSection\Production\Models\Department;
use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\PickHourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Production\Models\Shift;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds all production data matching FE data.ts mock data exactly.
 * Optimized with batch inserts for performance.
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Production\Data\Seeders\ProductionSeeder_1"
 */
final class ProductionSeeder_1 extends Seeder
{
    private const SHIFT_HOURS = ['6h-7h', '7h-8h', '8h-9h', '9h-10h', '10h-11h', '11h-12h', '12h-13h', '13h-14h'];

    public function run(): void
    {
        // Clear existing data (order matters for FK constraints)
        HourlyIssue::query()->delete();
        HourlyRecord::query()->delete();
        PickHourlyRecord::query()->delete();
        Shift::query()->delete();
        Department::query()->delete();
        ProductionLine::query()->delete();

        // ═══════════════════════════════════════════════════════
        // 1. PRODUCTION LINES (batch insert)
        // ═══════════════════════════════════════════════════════
        ProductionLine::insert([
            ['code' => 'dtf1', 'label' => 'DTF 1', 'color' => '#f59e0b', 'building' => 'Building 1', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'dtf2', 'label' => 'DTF 2', 'color' => '#14b8a6', 'building' => 'Building 2', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'dtg', 'label' => 'DTG', 'color' => '#8b5cf6', 'building' => null, 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $dtf1 = ProductionLine::where('code', 'dtf1')->first();
        $dtf2 = ProductionLine::where('code', 'dtf2')->first();
        $dtg = ProductionLine::where('code', 'dtg')->first();

        // ═══════════════════════════════════════════════════════
        // 2. DEPARTMENTS (batch insert)
        // ═══════════════════════════════════════════════════════
        $now = now();
        Department::insert([
            // DTF1 (4)
            ['production_line_id' => $dtf1->id, 'code' => 'print', 'label' => 'In ấn', 'label_en' => 'Print', 'icon' => 'Printer', 'unit' => 'files', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf1->id, 'code' => 'cut', 'label' => 'Cắt', 'label_en' => 'Cut', 'icon' => 'Scissors', 'unit' => 'files', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf1->id, 'code' => 'mockup', 'label' => 'Ráp mẫu', 'label_en' => 'Mock Up', 'icon' => 'Layers', 'unit' => 'files', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf1->id, 'code' => 'pack_ship', 'label' => 'Đóng gói & Giao', 'label_en' => 'Pack & Ship', 'icon' => 'Package', 'unit' => 'áo', 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            // DTF2 (4)
            ['production_line_id' => $dtf2->id, 'code' => 'print', 'label' => 'In ấn', 'label_en' => 'Print', 'icon' => 'Printer', 'unit' => 'files', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf2->id, 'code' => 'cut', 'label' => 'Cắt', 'label_en' => 'Cut', 'icon' => 'Scissors', 'unit' => 'files', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf2->id, 'code' => 'mockup', 'label' => 'Ráp mẫu', 'label_en' => 'Mock Up', 'icon' => 'Layers', 'unit' => 'files', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['production_line_id' => $dtf2->id, 'code' => 'pack_ship', 'label' => 'Đóng gói & Giao', 'label_en' => 'Pack & Ship', 'icon' => 'Package', 'unit' => 'áo', 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            // DTG (1)
            ['production_line_id' => $dtg->id, 'code' => 'print', 'label' => 'In ấn DTG', 'label_en' => 'DTG Print', 'icon' => 'Printer', 'unit' => 'prints', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Re-fetch departments with IDs
        $departments = Department::all()->keyBy(fn ($d) => "{$d->production_line_id}-{$d->code}");

        // ═══════════════════════════════════════════════════════
        // 3. SHIFT (today, shift 1)
        // ═══════════════════════════════════════════════════════
        $shift = Shift::create([
            'date' => now()->toDateString(),
            'shift_number' => 1,
            'start_time' => '06:00',
            'end_time' => '14:00',
            'supervisor' => 'Nguyễn Văn Minh',
            'is_active' => true,
        ]);

        // ═══════════════════════════════════════════════════════
        // 4. HOURLY RECORDS — batch insert (72 records in 1 query)
        // ═══════════════════════════════════════════════════════

        // [dept_key, staff, efficiency, errorRate, [target, actual] x 8]
        $hourlyData = [
            ["{$dtf1->id}-print", 12, 94.2, 2.1, [[94,95],[90,94],[97,95],[92,87],[94,82],[97,49],[97,null],[93,null]]],
            ["{$dtf1->id}-cut", 8, 91.5, 1.4, [[97,92],[99,99],[102,105],[102,98],[98,89],[102,49],[96,null],[97,null]]],
            ["{$dtf1->id}-mockup", 10, 96.8, 0.8, [[84,92],[93,100],[91,92],[92,91],[86,81],[94,48],[85,null],[90,null]]],
            ["{$dtf1->id}-pack_ship", 14, 87.3, 3.2, [[83,73],[81,74],[87,83],[86,86],[85,79],[83,41],[89,null],[82,null]]],
            ["{$dtf2->id}-print", 10, 92.1, 1.8, [[88,90],[85,88],[90,87],[87,85],[89,80],[91,45],[88,null],[86,null]]],
            ["{$dtf2->id}-cut", 6, 93.7, 1.1, [[82,85],[84,86],[86,88],[85,82],[83,78],[87,43],[84,null],[81,null]]],
            ["{$dtf2->id}-mockup", 8, 95.2, 1.0, [[75,78],[78,80],[76,77],[79,76],[74,70],[80,40],[77,null],[75,null]]],
            ["{$dtf2->id}-pack_ship", 10, 89.5, 2.5, [[70,65],[72,68],[74,72],[73,71],[71,66],[75,37],[72,null],[70,null]]],
            ["{$dtg->id}-print", 6, 88.4, 2.8, [[400,385],[400,392],[400,410],[400,378],[400,365],[400,195],[400,null],[400,null]]],
        ];

        $hourlyRecords = [];
        foreach ($hourlyData as [$deptKey, $staff, $efficiency, $errorRate, $hours]) {
            $dept = $departments->get($deptKey);
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

        // Single batch insert — 72 records in 1 query
        HourlyRecord::insert($hourlyRecords);

        // ═══════════════════════════════════════════════════════
        // 4b. HOURLY ISSUES — batch insert for missed hours
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

        // ═══════════════════════════════════════════════════════
        // 5. PICK HOURLY RECORDS — batch insert (24 records)
        // ═══════════════════════════════════════════════════════

        $pickData = [
            [$dtf1->id, 3, 92.0, 1.2, 780, [[160,155],[160,162],[165,168],[165,158],[160,150],[160,80],[165,null],[165,null]]],
            [$dtf2->id, 3, 90.5, 1.8, 640, [[135,130],[135,132],[140,142],[140,135],[135,128],[135,68],[140,null],[140,null]]],
            [$dtg->id, 2, 91.0, 1.5, 436, [[105,95],[105,101],[95,100],[95,95],[105,92],[105,47],[95,null],[95,null]]],
        ];

        $pickRecords = [];
        foreach ($pickData as [$lineId, $staff, $efficiency, $errorRate, $totalPicked, $hours]) {
            foreach ($hours as $i => [$target, $actual]) {
                $pickRecords[] = [
                    'shift_id' => $shift->id,
                    'production_line_id' => $lineId,
                    'hour_slot' => self::SHIFT_HOURS[$i],
                    'hour_index' => $i,
                    'target' => $target,
                    'actual' => $actual,
                    'staff' => $staff,
                    'efficiency' => $efficiency,
                    'error_rate' => $errorRate,
                    'total_picked' => $actual !== null ? $totalPicked : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Single batch insert — 24 records
        PickHourlyRecord::insert($pickRecords);
    }
}
