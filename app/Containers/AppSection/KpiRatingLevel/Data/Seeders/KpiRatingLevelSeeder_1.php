<?php

namespace App\Containers\AppSection\KpiRatingLevel\Data\Seeders;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevelDetail;
use App\Ship\Parents\Seeders\Seeder;

/**
 * Seeds default KPI Rating Levels matching the mockup design.
 *
 * 3 records:
 * - Mức đánh giá 2026 (active)   — Apr 2025, no end date
 * - Mức đánh giá 2024 (expired)  — Jan 2024 → Mar 2025
 * - Mức đánh giá 2021 (expired)  — Jan 2021 → Dec 2024
 *
 * Run: php artisan db:seed --class="App\Containers\AppSection\KpiRatingLevel\Data\Seeders\KpiRatingLevelSeeder_1"
 */
final class KpiRatingLevelSeeder_1 extends Seeder
{
    public function run(): void
    {
        if (KpiRatingLevel::count() > 0) {
            return;
        }

        // ═══════════════════════════════════════════════════════
        // 1. Mức đánh giá 2026 — Active (Apr 2025 → vô thời hạn)
        // ═══════════════════════════════════════════════════════
        $level2026 = KpiRatingLevel::create([
            'name'            => 'Mức đánh giá 2026',
            'effective_from'  => '2025-04-01',
            'effective_until' => null,
            'description'     => null,
        ]);

        $this->seedDetails($level2026->id, [
            //                                                             requires_reason  warn_staff
            ['Xuất sắc',   '#006400', '#FFFFFF', 100, '>=', false, false, 1],
            ['Đạt',        '#228B22', '#FFFFFF', 95,  '>=', false, false, 2],
            ['Trung bình', '#DAA520', '#FFFFFF', 90,  '>=', true,  true,  3],
            ['Yếu',        '#8B4513', '#FFFFFF', 85,  '>=', true,  true,  4],
            ['Chưa đạt',   '#8B0000', '#FFFFFF', 85,  '<',  true,  true,  5],
        ]);

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
            ['Xuất sắc',   '#006400', '#FFFFFF', 100, '>=', false, false, 1],
            ['Đạt',        '#228B22', '#FFFFFF', 90,  '>=', false, false, 2],
            ['Trung bình', '#DAA520', '#FFFFFF', 80,  '>=', true,  true,  3],
            ['Yếu',        '#8B4513', '#FFFFFF', 70,  '>=', true,  true,  4],
            ['Chưa đạt',   '#8B0000', '#FFFFFF', 70,  '<',  true,  true,  5],
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
            ['Xuất sắc',   '#006400', '#FFFFFF', 95, '>=', false, false, 1],
            ['Đạt',        '#228B22', '#FFFFFF', 90, '>=', false, false, 2],
            ['Trung bình', '#DAA520', '#FFFFFF', 85, '>=', true,  true,  3],
            ['Yếu',        '#8B4513', '#FFFFFF', 80, '>=', true,  true,  4],
            ['Chưa đạt',   '#8B0000', '#FFFFFF', 80, '<',  true,  true,  5],
        ]);
    }

    /**
     * Seed detail rows for a rating level.
     *
     * @param int   $ratingLevelId
     * @param array $rows  Each: [level_name, bg_color, text_color, min_score, operator, requires_reason, warn_staff_shortage, sort_order]
     */
    private function seedDetails(int $ratingLevelId, array $rows): void
    {
        foreach ($rows as [$levelName, $bgColor, $textColor, $minScore, $operator, $requiresReason, $warnStaffShortage, $sortOrder]) {
            KpiRatingLevelDetail::create([
                'rating_level_id'    => $ratingLevelId,
                'level_name'         => $levelName,
                'bg_color'           => $bgColor,
                'text_color'         => $textColor,
                'min_score'          => $minScore,
                'operator'           => $operator,
                'requires_reason'    => $requiresReason,
                'warn_staff_shortage'=> $warnStaffShortage,
                'sort_order'         => $sortOrder,
            ]);
        }
    }
}
