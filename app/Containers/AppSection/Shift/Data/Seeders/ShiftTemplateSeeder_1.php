<?php

namespace App\Containers\AppSection\Shift\Data\Seeders;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds default Shift Templates matching the mockup design.
 *
 * 5 templates:
 * 1. Ca chuẩn - bình thường (active)   — Ca 1 only
 * 2. Ca chuẩn - tăng ca     (active)   — Ca 1 only
 * 3. Ca chuẩn - ngày lễ     (inactive) — Ca 1 only
 * 4. Ca chuẩn - 2 ca         (inactive) — Ca 1 + Ca 2
 * 5. Ca chuẩn - 2 ca - tăng ca (inactive) — Ca 1 + Ca 2
 *
 * Default headcount = 0 for all details.
 * Default work_hours = 8 for all shifts.
 *
 * Break convention (per mockup):
 *   break1 = 15 min (nghỉ giải lao 1)
 *   meal   = 30 min (nghỉ ăn)
 *   break2 = 15 min (nghỉ giải lao 2)
 *   break3 = 15 min (nghỉ giải lao 3)
 *
 * end_time = start_time + work_hours + meal_break_minutes (computed by accessor)
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Shift\Data\Seeders\ShiftTemplateSeeder_1"
 */
final class ShiftTemplateSeeder_1 extends Seeder
{
    public function run(): void
    {
        // Fetch departments keyed by "lineCode-deptCode"
        $departments = Department::with('productionLine')->get()
            ->keyBy(fn ($d) => "{$d->productionLine->code}-{$d->code}");

        // ═══════════════════════════════════════════════════════
        // Standard Ca 1 schedule (from mockup)
        // [deptKey, shift, headcount, start, hours, prep,
        //  b1_start, b1_min, meal_start, meal_min,
        //  b2_start, b2_min, b3_start, b3_min]
        // ═══════════════════════════════════════════════════════
        $ca1 = [
            // Pick
            ['pick-dtf1',      1, 0, '06:00', 8, 0,  '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
            ['pick-dtf2',      1, 0, '06:00', 8, 0,  '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
            ['pick-dtg',       1, 0, '06:00', 8, 0,  '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
            // DTF1
            ['dtf1-print',     1, 0, '06:30', 8, 23, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
            ['dtf1-cut',       1, 0, '07:00', 8, 0,  '09:30', 15, '12:00', 30, '14:30', 15, '17:00', 15],
            ['dtf1-mockup',    1, 0, '07:30', 8, 0,  '10:00', 15, '12:30', 30, '15:00', 15, '17:30', 15],
            ['dtf1-pack_ship', 1, 0, '08:00', 8, 0,  '10:30', 15, '13:00', 30, '15:30', 15, '18:00', 15],
            // DTF2
            ['dtf2-print',     1, 0, '06:30', 8, 23, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
            ['dtf2-cut',       1, 0, '07:00', 8, 0,  '09:30', 15, '12:00', 30, '14:30', 15, '17:00', 15],
            ['dtf2-mockup',    1, 0, '07:30', 8, 0,  '10:00', 15, '12:30', 30, '15:00', 15, '17:30', 15],
            ['dtf2-pack_ship', 1, 0, '08:00', 8, 0,  '10:30', 15, '13:00', 30, '15:30', 15, '18:00', 15],
            // DTG
            ['dtg-apollo',     1, 0, '06:30', 8, 20, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
            ['dtg-atlas_01',   1, 0, '06:30', 8, 20, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
            ['dtg-atlas_02',   1, 0, '06:30', 8, 20, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
        ];

        // ═══════════════════════════════════════════════════════
        // Standard Ca 2 schedule (from mockup)
        // ═══════════════════════════════════════════════════════
        $ca2 = [
            // Pick
            ['pick-dtf1',      2, 0, '14:30', 8, 0,  '17:00', 15, '19:30', 30, '22:00', 15, '00:30', 15],
            ['pick-dtf2',      2, 0, '14:30', 8, 0,  '17:00', 15, '19:30', 30, '22:00', 15, '00:30', 15],
            ['pick-dtg',       2, 0, '14:30', 8, 0,  '17:00', 15, '19:30', 30, '22:00', 15, '00:30', 15],
            // DTF1
            ['dtf1-print',     2, 0, '15:00', 8, 23, '17:30', 15, '20:00', 30, '22:30', 15, '01:00', 15],
            ['dtf1-cut',       2, 0, '15:30', 8, 0,  '18:00', 15, '20:30', 30, '23:00', 15, '01:30', 15],
            ['dtf1-mockup',    2, 0, '16:00', 8, 0,  '18:30', 15, '21:00', 30, '23:30', 15, '02:00', 15],
            ['dtf1-pack_ship', 2, 0, '16:30', 8, 0,  '19:00', 15, '21:30', 30, '00:00', 15, '02:30', 15],
            // DTF2
            ['dtf2-print',     2, 0, '15:00', 8, 23, '17:30', 15, '20:00', 30, '22:30', 15, '01:00', 15],
            ['dtf2-cut',       2, 0, '15:30', 8, 0,  '18:00', 15, '20:30', 30, '23:00', 15, '01:30', 15],
            ['dtf2-mockup',    2, 0, '16:00', 8, 0,  '18:30', 15, '21:00', 30, '23:30', 15, '02:00', 15],
            ['dtf2-pack_ship', 2, 0, '16:30', 8, 0,  '19:00', 15, '21:30', 30, '00:00', 15, '02:30', 15],
            // DTG
            ['dtg-apollo',     2, 0, '15:00', 8, 20, '17:30', 15, '20:00', 30, '22:30', 15, '01:00', 15],
            ['dtg-atlas_01',   2, 0, '15:00', 8, 20, '17:30', 15, '20:00', 30, '22:30', 15, '01:00', 15],
            ['dtg-atlas_02',   2, 0, '15:00', 8, 20, '17:30', 15, '20:00', 30, '22:30', 15, '01:00', 15],
        ];

        // ═══════════════════════════════════════════════════════
        // Seed templates — firstOrCreate + seed details only if missing
        // ═══════════════════════════════════════════════════════
        $templates = [
            ['name' => 'Ca chuẩn - bình thường',    'color' => '#0000FF', 'description' => 'Dành cho ngày làm việc bình thường, làm việc từ 6h',        'sort_order' => 1, 'status' => 'active',   'applies_to_shift_1' => true, 'applies_to_shift_2' => false, 'details' => $ca1],
            ['name' => 'Ca chuẩn - tăng ca',        'color' => '#FF0000', 'description' => 'Dành cho các ngày sự kiện nhiều đơn - làm việc từ 5h',      'sort_order' => 2, 'status' => 'active',   'applies_to_shift_1' => true, 'applies_to_shift_2' => false, 'details' => $ca1],
            ['name' => 'Ca chuẩn - ngày lễ',        'color' => '#FFA500', 'description' => 'Dành cho các ngày lễ - ít người làm việc',                  'sort_order' => 3, 'status' => 'inactive', 'applies_to_shift_1' => true, 'applies_to_shift_2' => false, 'details' => $ca1],
            ['name' => 'Ca chuẩn - 2 ca',           'color' => '#008000', 'description' => 'Tăng ca thường',                                            'sort_order' => 4, 'status' => 'inactive', 'applies_to_shift_1' => true, 'applies_to_shift_2' => true,  'details' => array_merge($ca1, $ca2)],
            ['name' => 'Ca chuẩn - 2 ca - tăng ca', 'color' => '#800080', 'description' => 'Tăng ca sự kiện',                                           'sort_order' => 5, 'status' => 'inactive', 'applies_to_shift_1' => true, 'applies_to_shift_2' => true,  'details' => array_merge($ca1, $ca2)],
        ];

        foreach ($templates as $tplData) {
            $details = $tplData['details'];
            unset($tplData['details']);

            $template = ShiftTemplate::firstOrCreate(
                ['name' => $tplData['name']],
                $tplData,
            );

            // Only seed details if this template has none yet
            if ($template->details()->count() === 0) {
                $this->seedDetailsForTemplate($template->id, $departments, $details);
            }
        }
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
