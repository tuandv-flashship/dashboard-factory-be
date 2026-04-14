<?php

namespace App\Containers\AppSection\Shift\Data\Seeders;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Data\Seeders\ProductionSeeder_1;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds default Shift Templates matching the mockup design.
 *
 * Uses config('factory.current') to determine which departments get templates.
 *
 * FLS departments: DTF (Print, Pick, Cut, Mockup, Pack & Ship)
 * PD  departments: DTF (Print, Pick, Cut, Mockup) + DTG (Pick DTG, DTG Print group) + Pack & Ship
 *
 * 2 templates:
 * 1. Ca 1 (active) — shift_number = 1 only, color blue
 * 2. Ca 2 (active) — shift_number = 2 only, color red
 *
 * Default headcount = 0 for all details.
 * Default work_hours = 8 for all shifts.
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\Shift\Data\Seeders\ShiftTemplateSeeder_1"
 */
final class ShiftTemplateSeeder_1 extends Seeder
{
    public function run(): void
    {
        // Ensure production lines + departments exist (both seeders are idempotent).
        // ProductionSeeder_1 internally calls DepartmentSeeder_2 + MachineSeeder_2.
        $this->callOnce(ProductionSeeder_1::class);

        $factory = config('factory.current');

        // Fetch departments keyed by "lineCode-deptCode"
        $departments = Department::with('productionLine')->get()
            ->keyBy(fn ($d) => "{$d->productionLine->code}-{$d->code}");

        // ═══════════════════════════════════════════════════════
        // Ca 1 schedule (shift_number = 1)
        // [deptKey, shift, headcount, start, hours, prep,
        //  b1_start, b1_min, meal_start, meal_min,
        //  b2_start, b2_min, b3_start, b3_min]
        // ═══════════════════════════════════════════════════════
        $ca1 = match ($factory) {
            'FLS' => [
                // DTF — 5 departments
                ['dtf-print',     1, 0, '06:30', 8, 23, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
                ['dtf-pick',      1, 0, '06:00', 8, 0,  '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
                ['dtf-cut',       1, 0, '07:00', 8, 0,  '09:30', 15, '12:00', 30, '14:30', 15, '17:00', 15],
                ['dtf-mockup',    1, 0, '07:30', 8, 0,  '10:00', 15, '12:30', 30, '15:00', 15, '17:30', 15],
                ['dtf-pack_ship', 1, 0, '08:00', 8, 0,  '10:30', 15, '13:00', 30, '15:30', 15, '18:00', 15],
            ],
            'PD' => [
                // DTF — 4 departments
                ['dtf-print',     1, 0, '06:30', 8, 23, '09:00', 15, '11:30', 30, '14:00', 15, '16:30', 15],
                ['dtf-pick',      1, 0, '06:00', 8, 0,  '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
                ['dtf-cut',       1, 0, '07:00', 8, 0,  '09:30', 15, '12:00', 30, '14:30', 15, '17:00', 15],
                ['dtf-mockup',    1, 0, '07:30', 8, 0,  '10:00', 15, '12:30', 30, '15:00', 15, '17:30', 15],
                // DTG — 2 departments
                ['dtg-pick_dtg',  1, 0, '06:00', 8, 0,  '08:30', 15, '11:00', 30, '13:30', 15, '16:00', 15],
                ['dtg-dtg_print', 1, 0, '06:30', 8, 20, '09:00', 15, '11:00', 30, '14:00', 15, '16:30', 15],
                // Pack & Ship — 1 department
                ['pack_ship-pack_ship', 1, 0, '08:00', 8, 0, '10:30', 15, '13:00', 30, '15:30', 15, '18:00', 15],
            ],
        };

        // ═══════════════════════════════════════════════════════
        // Ca 2 schedule (shift_number = 2)
        // ═══════════════════════════════════════════════════════
        $ca2 = match ($factory) {
            'FLS' => [
                // DTF — 5 departments
                ['dtf-print',     2, 0, '15:00', 8, 0, '17:30', 15, '20:00', 30, '22:30', 15, '01:00', 15],
                ['dtf-pick',      2, 0, '14:30', 8, 0, '17:00', 15, '19:30', 30, '22:00', 15, '00:30', 15],
                ['dtf-cut',       2, 0, '15:30', 8, 0, '18:00', 15, '20:30', 30, '23:00', 15, '01:30', 15],
                ['dtf-mockup',    2, 0, '16:00', 8, 0, '18:30', 15, '21:00', 30, '23:30', 15, '02:00', 15],
                ['dtf-pack_ship', 2, 0, '16:30', 8, 0, '19:00', 15, '21:30', 30, '00:00', 15, '02:30', 15],
            ],
            'PD' => [
                // DTF — 4 departments
                ['dtf-print',     2, 0, '15:00', 8, 0, '17:30', 15, '20:00', 30, '22:30', 15, '01:00', 15],
                ['dtf-pick',      2, 0, '14:30', 8, 0, '17:00', 15, '19:30', 30, '22:00', 15, '00:30', 15],
                ['dtf-cut',       2, 0, '15:30', 8, 0, '18:00', 15, '20:30', 30, '23:00', 15, '01:30', 15],
                ['dtf-mockup',    2, 0, '16:00', 8, 0, '18:30', 15, '21:00', 30, '23:30', 15, '02:00', 15],
                // DTG — 2 departments
                ['dtg-pick_dtg',  2, 0, '14:30', 8, 0, '17:00', 15, '19:30', 30, '22:00', 15, '00:30', 15],
                ['dtg-dtg_print', 2, 0, '15:00', 8, 0, '17:30', 15, '20:00', 30, '22:30', 15, '01:00', 15],
                // Pack & Ship — 1 department
                ['pack_ship-pack_ship', 2, 0, '16:30', 8, 0, '19:00', 15, '21:30', 30, '00:00', 15, '02:30', 15],
            ],
        };

        // ═══════════════════════════════════════════════════════
        // Seed 2 templates — firstOrCreate + seed details only if missing
        // ═══════════════════════════════════════════════════════
        $templates = [
            ['name' => 'Ca 1', 'color' => '#0000FF', 'description' => 'Dành cho ngày làm việc bình thường. làm việc từ 6h',        'sort_order' => 1, 'status' => 'active', 'applies_to_shift_1' => true, 'applies_to_shift_2' => false, 'details' => $ca1],
            ['name' => 'Ca 2', 'color' => '#FF0000', 'description' => 'Dành cho các ngày sự kiện nhiều đơn - làm việc từ 5h',      'sort_order' => 2, 'status' => 'active', 'applies_to_shift_1' => false, 'applies_to_shift_2' => true,  'details' => $ca2],
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
