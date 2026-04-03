<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tồn đầu/cuối ngày) for team IN - DTG.
 *
 * Source: docs/ton_dau_ngay_update.sql lines 437-465
 * Uses: dtg_item_detail + dtg_printed_product (no factory param)
 */
final class GetDtgPrintInventoryTask extends ParentTask
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
            )
            SELECT estimate_folder_date AS estimate_date,
                ton_dau,
                ton_cuoi
            FROM (
                SELECT
                    estimate_folder_date,
                    total_file + COALESCE(SUM(unprint_file) OVER (
                        ORDER BY estimate_folder_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS ton_dau,
                    SUM(unprint_file) OVER (ORDER BY estimate_folder_date) AS ton_cuoi
                FROM daily_aggregated
            ) c
            WHERE estimate_folder_date = ?
        ";

        return $this->formatResult($this->queryFplatform($sql, [$date, $date, $date]));
    }
}
