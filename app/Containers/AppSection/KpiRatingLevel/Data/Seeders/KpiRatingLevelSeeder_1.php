<?php

namespace App\Containers\AppSection\KpiRatingLevel\Data\Seeders;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevelDetail;
use App\Ship\Parents\Seeders\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds default KPI Rating Levels matching the mockup design.
 *
 * Usage:
 *   php artisan db:seed --class="App\Containers\AppSection\KpiRatingLevel\Data\Seeders\KpiRatingLevelSeeder_1"
 *
 * Force re-seed (truncate existing data first):
 *   php artisan db:seed --class="App\Containers\AppSection\KpiRatingLevel\Data\Seeders\KpiRatingLevelSeeder_1" -- --force
 */
final class KpiRatingLevelSeeder_1 extends Seeder
{
    public function run(): void
    {
        $force = $this->command?->hasOption('force') && $this->command->option('force');

        if ($force) {
            $this->command->info('Force mode: truncating existing KPI Rating Levels...');
            DB::transaction(function () {
                KpiRatingLevelDetail::query()->delete();
                KpiRatingLevel::query()->delete();
            });
        }

        if (KpiRatingLevel::count() > 0) {
            $this->command?->info('KPI Rating Levels already exist. Skipping. Use --force to re-seed.');
            return;
        }

        DB::transaction(function () {
            $this->seedLevels();
        });

        $this->command?->info('KPI Rating Levels seeded successfully.');
    }

    private function seedLevels(): void
    {
        // ═══════════════════════════════════════════════════════
        // 1. Mức đánh giá 2026 — Active (reuses config defaults)
        // ═══════════════════════════════════════════════════════
        $level2026 = KpiRatingLevel::create([
            'name'            => 'Mức đánh giá 2026',
            'effective_from'  => '2025-04-01',
            'effective_until' => null,
            'description'     => null,
        ]);

        // DRY: reuse the same defaults served by the API
        $this->seedDetailsFromConfig($level2026->id);

        // ═══════════════════════════════════════════════════════
        // 2. Mức đánh giá 2024 — Expired (Jan 2024 → Mar 2025)
        // ═══════════════════════════════════════════════════════
        $level2024 = KpiRatingLevel::create([
            'name'            => 'Mức đánh giá 2024',
            'effective_from'  => '2024-01-01',
            'effective_until' => '2025-03-31',
            'description'     => null,
        ]);

        $this->seedDetails($level2024->id, [
            ['Đạt',        '#4CAF50', '#FFFFFF', 90, '>=', true,  false, 1],
            ['Trung bình', '#FF9800', '#FFFFFF', 80, '>=', false, true,  2],
            ['Không đạt',  '#F44336', '#FFFFFF', 80, '<',  false, false, 3],
        ]);

        // ═══════════════════════════════════════════════════════
        // 3. Mức đánh giá 2021 — Expired (Jan 2021 → Dec 2024)
        // ═══════════════════════════════════════════════════════
        $level2021 = KpiRatingLevel::create([
            'name'            => 'Mức đánh giá 2021',
            'effective_from'  => '2021-01-01',
            'effective_until' => '2024-12-31',
            'description'     => null,
        ]);

        $this->seedDetails($level2021->id, [
            ['Đạt',        '#4CAF50', '#FFFFFF', 90, '>=', true,  false, 1],
            ['Trung bình', '#FF9800', '#FFFFFF', 85, '>=', false, true,  2],
            ['Không đạt',  '#F44336', '#FFFFFF', 85, '<',  false, false, 3],
        ]);
    }

    /**
     * Seed details from the config defaults (DRY with the API).
     */
    private function seedDetailsFromConfig(int $ratingLevelId): void
    {
        $defaults = config('appSection-kpiRatingLevel.default.details', []);

        foreach ($defaults as $row) {
            KpiRatingLevelDetail::create([
                'rating_level_id'            => $ratingLevelId,
                'level_name'                 => $row['level_name'],
                'bg_color'                   => $row['bg_color'],
                'text_color'                 => $row['text_color'],
                'min_score'                  => $row['min_score'],
                'operator'                   => $row['operator'],
                'is_kpi_threshold'           => $row['is_kpi_threshold'],
                'is_staff_warning_threshold' => $row['is_staff_warning_threshold'],
                'sort_order'                 => $row['sort_order'],
            ]);
        }
    }

    /**
     * Seed detail rows for a rating level.
     *
     * @param array $rows Each: [level_name, bg_color, text_color, min_score, operator, is_kpi_threshold, is_staff_warning_threshold, sort_order]
     */
    private function seedDetails(int $ratingLevelId, array $rows): void
    {
        foreach ($rows as [$levelName, $bgColor, $textColor, $minScore, $operator, $isKpiThreshold, $isStaffWarning, $sortOrder]) {
            KpiRatingLevelDetail::create([
                'rating_level_id'            => $ratingLevelId,
                'level_name'                 => $levelName,
                'bg_color'                   => $bgColor,
                'text_color'                 => $textColor,
                'min_score'                  => $minScore,
                'operator'                   => $operator,
                'is_kpi_threshold'           => $isKpiThreshold,
                'is_staff_warning_threshold' => $isStaffWarning,
                'sort_order'                 => $sortOrder,
            ]);
        }
    }
}
