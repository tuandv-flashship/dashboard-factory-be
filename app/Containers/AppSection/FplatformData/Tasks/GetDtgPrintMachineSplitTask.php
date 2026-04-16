<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\Log;

/**
 * Get daily inventory for team IN - DTG, split by machine productivity ratio.
 *
 * Source: docs/rpt_factory_ops_metrics_v5.sql
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
                    ), 0) AS tong_viec
                FROM daily_aggregated
            )
            SELECT
                estimate_folder_date AS estimate_date,
                ROUND(tong_viec * 0.625) AS tong_viec_apollo,
                ROUND(tong_viec * 0.1875) AS tong_viec_atlas1,
                tong_viec - ROUND(tong_viec * 0.625) - ROUND(tong_viec * 0.1875) AS tong_viec_atlas2
            FROM base_data
            WHERE estimate_folder_date = ?
        ";

        $result = $this->queryFplatform($sql, [$date, $date, $date]);

        if (!$result) {
            return null;
        }

        return [
            'estimate_date' => $result->estimate_date,
            'machines'      => [
                'apollo' => [
                    'ratio'     => '62.5%',
                    'tong_viec' => (int) $result->tong_viec_apollo,
                ],
                'atlas_1' => [
                    'ratio'     => '18.75%',
                    'tong_viec' => (int) $result->tong_viec_atlas1,
                ],
                'atlas_2' => [
                    'ratio'     => '18.75%',
                    'tong_viec' => (int) $result->tong_viec_atlas2,
                ],
            ],
        ];
    }
}
