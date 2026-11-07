<?php

namespace App\Containers\AppSection\FplatformData\Support;

/**
 * Split a daily DTG inventory total across machines proportionally to their KPI/hour.
 *
 * Replaces the former hardcoded 62.5% / 18.75% / 18.75% split (Apollo / ATLAS_1 / ATLAS_2):
 * the machine list now comes from the `machines` table (active machines of dtg_print).
 */
final class DtgMachineSplitter
{
    /**
     * @param  int                $tongViec   Total inventory to split.
     * @param  array<string,int>  $kpiByCode  [machine_code => kpi_per_hour], in display order.
     * @return array<string, array{ratio: string, tong_viec: int}>
     *         Keyed by machine code, same order as input. Last machine absorbs the rounding
     *         remainder so that Σ tong_viec === $tongViec. Empty when no machine has KPI > 0.
     */
    public static function split(int $tongViec, array $kpiByCode): array
    {
        $totalKpi = array_sum($kpiByCode);
        if ($totalKpi <= 0) {
            return [];
        }

        $result    = [];
        $allocated = 0;
        $codes     = array_keys($kpiByCode);
        $lastCode  = end($codes);

        foreach ($kpiByCode as $code => $kpi) {
            $share = $kpi / $totalKpi;
            $part  = $code === $lastCode
                ? $tongViec - $allocated
                : (int) round($tongViec * $share);
            $allocated += $part;

            $result[$code] = [
                'ratio'     => self::formatRatio($share * 100),
                'tong_viec' => $part,
            ];
        }

        return $result;
    }

    private static function formatRatio(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.') . '%';
    }
}
