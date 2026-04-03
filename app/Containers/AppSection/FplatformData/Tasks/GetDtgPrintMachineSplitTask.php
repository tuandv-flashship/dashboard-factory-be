<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\Log;

/**
 * Get daily inventory for team IN - DTG, split by machine productivity ratio.
 *
 * Source: docs/ton_dau_ngay_update.sql lines 474-521
 * Apollo 250 file/h (62.5%), ATLAS_1 75 file/h (18.75%), ATLAS_2 75 file/h (18.75%)
 */
final class GetDtgPrintMachineSplitTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date): ?array
    {
        $sql = "
            WITH daily_aggregated AS (
                SELECT
                    d.estimate_folder_date,
                    COUNT(*) AS total_file,
                    SUM(IF(p.print_status = 0 OR p.print_status IS NULL, 1, 0)) AS unprint_file
                FROM dtg_item_detail d
                LEFT JOIN dtg_printed_product p
                    ON d.order_code = p.order_code
                    AND d.index_num = p.index_num
                    AND d.distribute_id = p.distribute_id
                WHERE d.estimate_folder_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND d.active = 1
                GROUP BY d.estimate_folder_date
            ),
            base_data AS (
                SELECT
                    estimate_folder_date,
                    total_file + COALESCE(SUM(unprint_file) OVER (
                        ORDER BY estimate_folder_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS ton_dau,
                    SUM(unprint_file) OVER (ORDER BY estimate_folder_date) AS ton_cuoi
                FROM daily_aggregated
            ),
            split_logic AS (
                SELECT
                    estimate_folder_date,
                    ROUND(ton_dau * 0.625) AS td_apollo,
                    ROUND(ton_cuoi * 0.625) AS tc_apollo,
                    ROUND(ton_dau * 0.1875) AS td_atlas1,
                    ROUND(ton_cuoi * 0.1875) AS tc_atlas1,
                    ton_dau - ROUND(ton_dau * 0.625) - ROUND(ton_dau * 0.1875) AS td_atlas2,
                    ton_cuoi - ROUND(ton_cuoi * 0.625) - ROUND(ton_cuoi * 0.1875) AS tc_atlas2
                FROM base_data
                WHERE estimate_folder_date = ?
            )
            SELECT
                estimate_folder_date AS estimate_date,
                td_apollo AS ton_dau_apollo,
                td_atlas1 AS ton_dau_atlas1,
                td_atlas2 AS ton_dau_atlas2,
                tc_apollo AS ton_cuoi_apollo,
                tc_atlas1 AS ton_cuoi_atlas1,
                tc_atlas2 AS ton_cuoi_atlas2
            FROM split_logic
        ";

        $result = $this->queryFplatform($sql, [$date, $date, $date]);

        if (!$result) {
            return null;
        }

        return [
            'estimate_date' => $result->estimate_date,
            'machines'      => [
                'apollo' => [
                    'ratio'    => '62.5%',
                    'ton_dau'  => (int) $result->ton_dau_apollo,
                    'ton_cuoi' => (int) $result->ton_cuoi_apollo,
                ],
                'atlas_1' => [
                    'ratio'    => '18.75%',
                    'ton_dau'  => (int) $result->ton_dau_atlas1,
                    'ton_cuoi' => (int) $result->ton_cuoi_atlas1,
                ],
                'atlas_2' => [
                    'ratio'    => '18.75%',
                    'ton_dau'  => (int) $result->ton_dau_atlas2,
                    'ton_cuoi' => (int) $result->ton_cuoi_atlas2,
                ],
            ],
        ];
    }
}
