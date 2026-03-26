<?php

namespace App\Containers\AppSection\Shift\Data\Seeders;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds default Shift Templates matching the mockup design.
 *
 * 5 templates:
 * 1. Ca chuẩn - bình thường (active)   — 8h work
 * 2. Ca chuẩn - tăng ca     (active)   — 10h work
 * 3. Ca chuẩn - ngày lễ     (inactive) — 7h work
 * 4. Ca chuẩn - 2 ca         (inactive) — 8h work × 2 ca
 * 5. Ca chuẩn - 2 ca - tăng ca (inactive) — 8h + 9h
 *
 * Break convention (per mockup):
 *   break1 = 15 min (nghỉ giải lao 1)
 *   meal   = 30 min (nghỉ ăn)
 *   break2 = 15 min (nghỉ giải lao 2)
 *   break3 = 15 min (nghỉ giải lao 3, optional)
 *
 * end_time = start_time + work_hours + meal_break_minutes (computed by accessor)
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Shift\Data\Seeders\ShiftTemplateSeeder_1"
 */
final class ShiftTemplateSeeder_1 extends Seeder
{
    public function run(): void
    {
        if (ShiftTemplate::count() > 0) {
            return;
        }

        // Fetch departments keyed by "lineCode-deptCode"
        $departments = Department::with('productionLine')->get()
            ->keyBy(fn ($d) => "{$d->productionLine->code}-{$d->code}");

        // ═══════════════════════════════════════════════════════
        // 1. Ca chuẩn - bình thường (Active, Ca 1 only)
        //    8h work + 30min meal = 8h30 presence
        // ═══════════════════════════════════════════════════════
        $t1 = ShiftTemplate::create([
            'name'                => 'Ca chuẩn - bình thường',
            'color'               => '#0000FF',
            'description'         => 'Dành cho ngày làm việc bình thường, làm việc từ 6h',
            'sort_order'          => 1,
            'status'              => 'active',
            'applies_to_shift_1'  => true,
            'applies_to_shift_2'  => false,
        ]);

        $this->seedDetailsForTemplate($t1->id, $departments, [
            // [lineCode-deptCode, shift, headcount, start, hours, prep, break1_start, break1_min, meal_start, meal_min, break2_start, break2_min, break3_start, break3_min]
            ['pick-dtf1',      1, 3, '06:00', 8, 0, '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
            ['pick-dtf2',      1, 3, '06:00', 8, 0, '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
            ['pick-dtg',       1, 3, '06:00', 8, 0, '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
            ['dtf1-print',     1, 8, '06:30', 8, 23, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
            ['dtf1-cut',       1, 5, '07:00', 8, 0, '09:30', 15, '12:00', 30, '14:30', 15, '17:00', 15],
            ['dtf1-mockup',    1, 15, '07:30', 8, 0, '10:00', 15, '12:30', 30, '15:00', 15, '17:30', 15],
            ['dtf1-pack_ship', 1, 7, '08:00', 8, 0, '10:30', 15, '13:00', 30, '15:30', 15, '18:00', 15],
            ['dtf2-print',     1, 8, '06:30', 8, 23, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
            ['dtf2-cut',       1, 5, '07:00', 8, 0, '09:30', 15, '12:00', 30, '14:30', 15, '17:00', 15],
            ['dtf2-mockup',    1, 15, '07:30', 8, 0, '10:00', 15, '12:30', 30, '15:00', 15, '17:30', 15],
            ['dtf2-pack_ship', 1, 7, '08:00', 8, 0, '10:30', 15, '13:00', 30, '15:30', 15, '18:00', 15],
            ['dtg-print',      1, 1, '06:30', 8, 20, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
        ]);

        // ═══════════════════════════════════════════════════════
        // 2. Ca chuẩn - tăng ca (Active, Ca 1 only)
        //    10h work + 30min meal
        // ═══════════════════════════════════════════════════════
        $t2 = ShiftTemplate::create([
            'name'                => 'Ca chuẩn - tăng ca',
            'color'               => '#FF0000',
            'description'         => 'Dành cho các ngày sự kiện nhiều đơn - làm việc từ 5h',
            'sort_order'          => 2,
            'status'              => 'active',
            'applies_to_shift_1'  => true,
            'applies_to_shift_2'  => false,
        ]);

        $this->seedDetailsForTemplate($t2->id, $departments, [
            ['pick-dtf1',      1, 3, '06:00', 10, 0, '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
            ['pick-dtf2',      1, 3, '06:00', 10, 0, '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
            ['pick-dtg',       1, 3, '06:00', 10, 0, '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
            ['dtf1-print',     1, 8, '06:30', 10, 23, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
            ['dtf1-cut',       1, 5, '07:00', 10, 0, '09:30', 15, '12:00', 30, '14:30', 15, '17:00', 15],
            ['dtf1-mockup',    1, 15, '07:30', 10, 0, '10:00', 15, '12:30', 30, '15:00', 15, '17:30', 15],
            ['dtf1-pack_ship', 1, 7, '08:00', 10, 0, '10:30', 15, '13:00', 30, '15:30', 15, '18:00', 15],
            ['dtf2-print',     1, 8, '06:30', 10, 23, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
            ['dtf2-cut',       1, 5, '07:00', 10, 0, '09:30', 15, '12:00', 30, '14:30', 15, '17:00', 15],
            ['dtf2-mockup',    1, 15, '07:30', 10, 0, '10:00', 15, '12:30', 30, '15:00', 15, '17:30', 15],
            ['dtf2-pack_ship', 1, 7, '08:00', 10, 0, '10:30', 15, '13:00', 30, '15:30', 15, '18:00', 15],
            ['dtg-print',      1, 1, '06:30', 10, 20, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
        ]);

        // ═══════════════════════════════════════════════════════
        // 3. Ca chuẩn - ngày lễ (Inactive, Ca 1 only)
        //    7h work + 30min meal, ít người
        // ═══════════════════════════════════════════════════════
        $t3 = ShiftTemplate::create([
            'name'                => 'Ca chuẩn - ngày lễ',
            'color'               => '#FFA500',
            'description'         => 'Dành cho các ngày lễ - ít người làm việc',
            'sort_order'          => 3,
            'status'              => 'inactive',
            'applies_to_shift_1'  => true,
            'applies_to_shift_2'  => false,
        ]);

        $this->seedDetailsForTemplate($t3->id, $departments, [
            ['pick-dtf1',      1, 2, '07:00', 7, 0, '09:00', 15, '11:00', 30, '13:00', 15, null, 0],
            ['pick-dtf2',      1, 2, '07:00', 7, 0, '09:00', 15, '11:00', 30, '13:00', 15, null, 0],
            ['pick-dtg',       1, 2, '07:00', 7, 0, '09:00', 15, '11:00', 30, '13:00', 15, null, 0],
            ['dtf1-print',     1, 5, '07:00', 7, 15, '09:30', 15, '11:30', 30, '13:30', 15, null, 0],
            ['dtf1-cut',       1, 3, '07:30', 7, 0, '10:00', 15, '12:00', 30, '14:00', 15, null, 0],
            ['dtf1-mockup',    1, 8, '07:30', 7, 0, '10:00', 15, '12:00', 30, '14:00', 15, null, 0],
            ['dtf1-pack_ship', 1, 4, '08:00', 7, 0, '10:30', 15, '12:30', 30, '14:30', 15, null, 0],
            ['dtf2-print',     1, 5, '07:00', 7, 15, '09:30', 15, '11:30', 30, '13:30', 15, null, 0],
            ['dtf2-cut',       1, 3, '07:30', 7, 0, '10:00', 15, '12:00', 30, '14:00', 15, null, 0],
            ['dtf2-mockup',    1, 8, '07:30', 7, 0, '10:00', 15, '12:00', 30, '14:00', 15, null, 0],
            ['dtf2-pack_ship', 1, 4, '08:00', 7, 0, '10:30', 15, '12:30', 30, '14:30', 15, null, 0],
            ['dtg-print',      1, 1, '07:00', 7, 15, '09:30', 15, '11:30', 30, '13:30', 15, null, 0],
        ]);

        // ═══════════════════════════════════════════════════════
        // 4. Ca chuẩn - 2 ca (Inactive, Ca 1 + Ca 2)
        //    8h work + 30min meal per shift
        // ═══════════════════════════════════════════════════════
        $t4 = ShiftTemplate::create([
            'name'                => 'Ca chuẩn - 2 ca',
            'color'               => '#008000',
            'description'         => 'Tăng ca thường',
            'sort_order'          => 4,
            'status'              => 'inactive',
            'applies_to_shift_1'  => true,
            'applies_to_shift_2'  => true,
        ]);

        // Ca 1
        $this->seedDetailsForTemplate($t4->id, $departments, [
            ['pick-dtf1',      1, 3, '06:00', 8, 0, '08:30', 15, '11:00', 30, '13:30', 15, null, 0],
            ['pick-dtf2',      1, 3, '06:00', 8, 0, '08:30', 15, '11:00', 30, '13:30', 15, null, 0],
            ['pick-dtg',       1, 3, '06:00', 8, 0, '08:30', 15, '11:00', 30, '13:30', 15, null, 0],
            ['dtf1-print',     1, 8, '06:30', 8, 20, '09:00', 15, '11:30', 30, '14:00', 15, null, 0],
            ['dtf1-cut',       1, 5, '07:00', 8, 0, '09:30', 15, '12:00', 30, '14:30', 15, null, 0],
            ['dtf1-mockup',    1, 15, '07:30', 8, 0, '10:00', 15, '12:30', 30, '15:00', 15, null, 0],
            ['dtf1-pack_ship', 1, 7, '08:00', 8, 0, '10:30', 15, '13:00', 30, '15:30', 15, null, 0],
            ['dtf2-print',     1, 8, '06:30', 8, 20, '09:00', 15, '11:30', 30, '14:00', 15, null, 0],
            ['dtf2-cut',       1, 5, '07:00', 8, 0, '09:30', 15, '12:00', 30, '14:30', 15, null, 0],
            ['dtf2-mockup',    1, 15, '07:30', 8, 0, '10:00', 15, '12:30', 30, '15:00', 15, null, 0],
            ['dtf2-pack_ship', 1, 7, '08:00', 8, 0, '10:30', 15, '13:00', 30, '15:30', 15, null, 0],
            ['dtg-print',      1, 1, '06:30', 8, 20, '09:00', 15, '11:30', 30, '14:00', 15, null, 0],
        ]);

        // Ca 2
        $this->seedDetailsForTemplate($t4->id, $departments, [
            ['pick-dtf1',      2, 2, '14:00', 8, 0, '16:30', 15, '19:00', 30, '21:00', 15, null, 0],
            ['pick-dtf2',      2, 2, '14:00', 8, 0, '16:30', 15, '19:00', 30, '21:00', 15, null, 0],
            ['pick-dtg',       2, 2, '14:00', 8, 0, '16:30', 15, '19:00', 30, '21:00', 15, null, 0],
            ['dtf1-print',     2, 5, '14:30', 8, 15, '17:00', 15, '19:30', 30, '21:30', 15, null, 0],
            ['dtf1-cut',       2, 3, '15:00', 8, 0, '17:30', 15, '20:00', 30, '22:00', 15, null, 0],
            ['dtf1-mockup',    2, 10,'15:30', 8, 0, '18:00', 15, '20:30', 30, '22:30', 15, null, 0],
            ['dtf1-pack_ship', 2, 5, '16:00', 8, 0, '18:30', 15, '21:00', 30, '23:00', 15, null, 0],
            ['dtf2-print',     2, 5, '14:30', 8, 15, '17:00', 15, '19:30', 30, '21:30', 15, null, 0],
            ['dtf2-cut',       2, 3, '15:00', 8, 0, '17:30', 15, '20:00', 30, '22:00', 15, null, 0],
            ['dtf2-mockup',    2, 10,'15:30', 8, 0, '18:00', 15, '20:30', 30, '22:30', 15, null, 0],
            ['dtf2-pack_ship', 2, 5, '16:00', 8, 0, '18:30', 15, '21:00', 30, '23:00', 15, null, 0],
            ['dtg-print',      2, 1, '14:30', 8, 15, '17:00', 15, '19:30', 30, '21:30', 15, null, 0],
        ]);

        // ═══════════════════════════════════════════════════════
        // 5. Ca chuẩn - 2 ca - tăng ca (Inactive, Ca 1 + Ca 2)
        //    Ca 1: 8h work + 30min meal, Ca 2: 9h work + 30min meal
        // ═══════════════════════════════════════════════════════
        $t5 = ShiftTemplate::create([
            'name'                => 'Ca chuẩn - 2 ca - tăng ca',
            'color'               => '#800080',
            'description'         => 'Tăng ca sự kiện',
            'sort_order'          => 5,
            'status'              => 'inactive',
            'applies_to_shift_1'  => true,
            'applies_to_shift_2'  => true,
        ]);

        // Ca 1
        $this->seedDetailsForTemplate($t5->id, $departments, [
            ['pick-dtf1',      1, 3, '05:30', 8, 0, '08:00', 15, '10:30', 30, '13:00', 15, '15:30', 15],
            ['pick-dtf2',      1, 3, '05:30', 8, 0, '08:00', 15, '10:30', 30, '13:00', 15, '15:30', 15],
            ['pick-dtg',       1, 3, '05:30', 8, 0, '08:00', 15, '10:30', 30, '13:00', 15, '15:30', 15],
            ['dtf1-print',     1, 8, '06:00', 8, 23, '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
            ['dtf1-cut',       1, 5, '06:30', 8, 0, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
            ['dtf1-mockup',    1, 15, '07:00', 8, 0, '09:30', 15, '12:00', 30, '14:30', 15, '17:00', 15],
            ['dtf1-pack_ship', 1, 7, '07:30', 8, 0, '10:00', 15, '12:30', 30, '15:00', 15, '17:30', 15],
            ['dtf2-print',     1, 8, '06:00', 8, 23, '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
            ['dtf2-cut',       1, 5, '06:30', 8, 0, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
            ['dtf2-mockup',    1, 15, '07:00', 8, 0, '09:30', 15, '12:00', 30, '14:30', 15, '17:00', 15],
            ['dtf2-pack_ship', 1, 7, '07:30', 8, 0, '10:00', 15, '12:30', 30, '15:00', 15, '17:30', 15],
            ['dtg-print',      1, 1, '06:00', 8, 20, '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
        ]);

        // Ca 2
        $this->seedDetailsForTemplate($t5->id, $departments, [
            ['pick-dtf1',      2, 2, '14:00', 9, 0, '16:30', 15, '19:00', 30, '21:30', 15, null, 0],
            ['pick-dtf2',      2, 2, '14:00', 9, 0, '16:30', 15, '19:00', 30, '21:30', 15, null, 0],
            ['pick-dtg',       2, 2, '14:00', 9, 0, '16:30', 15, '19:00', 30, '21:30', 15, null, 0],
            ['dtf1-print',     2, 5, '14:30', 9, 15, '17:00', 15, '19:30', 30, '22:00', 15, null, 0],
            ['dtf1-cut',       2, 3, '15:00', 9, 0, '17:30', 15, '20:00', 30, '22:30', 15, null, 0],
            ['dtf1-mockup',    2, 10,'15:30', 9, 0, '18:00', 15, '20:30', 30, '23:00', 15, null, 0],
            ['dtf1-pack_ship', 2, 5, '16:00', 9, 0, '18:30', 15, '21:00', 30, '23:30', 15, null, 0],
            ['dtf2-print',     2, 5, '14:30', 9, 15, '17:00', 15, '19:30', 30, '22:00', 15, null, 0],
            ['dtf2-cut',       2, 3, '15:00', 9, 0, '17:30', 15, '20:00', 30, '22:30', 15, null, 0],
            ['dtf2-mockup',    2, 10,'15:30', 9, 0, '18:00', 15, '20:30', 30, '23:00', 15, null, 0],
            ['dtf2-pack_ship', 2, 5, '16:00', 9, 0, '18:30', 15, '21:00', 30, '23:30', 15, null, 0],
            ['dtg-print',      2, 1, '14:30', 9, 15, '17:00', 15, '19:30', 30, '22:00', 15, null, 0],
        ]);
    }

    /**
     * Seed detail rows for a shift template.
     *
     * @param int        $templateId
     * @param \Illuminate\Support\Collection $departments  keyed by "lineCode-deptCode"
     * @param array      $rows  Each: [deptKey, shift, headcount, start, hours, prep, b1_start, b1_min, meal_start, meal_min, b2_start, b2_min, b3_start, b3_min]
     */
    private function seedDetailsForTemplate(int $templateId, $departments, array $rows): void
    {
        foreach ($rows as [$deptKey, $shift, $headcount, $start, $hours, $prep, $b1Start, $b1Min, $mealStart, $mealMin, $b2Start, $b2Min, $b3Start, $b3Min]) {
            $dept = $departments->get($deptKey);
            if (! $dept) {
                continue; // skip if department not found
            }

            ShiftTemplateDetail::create([
                'shift_template_id' => $templateId,
                'department_id'     => $dept->id,
                'shift_number'      => $shift,
                'headcount'         => $headcount,
                'start_time'        => $start,
                'work_hours'        => $hours,
                'prep_minutes'      => $prep,
                'break1_start'      => $b1Start,
                'break1_minutes'    => $b1Min,
                'meal_break_start'  => $mealStart,
                'meal_break_minutes'=> $mealMin,
                'break2_start'      => $b2Start,
                'break2_minutes'    => $b2Min,
                'break3_start'      => $b3Start,
                'break3_minutes'    => $b3Min,
            ]);
        }
    }
}
