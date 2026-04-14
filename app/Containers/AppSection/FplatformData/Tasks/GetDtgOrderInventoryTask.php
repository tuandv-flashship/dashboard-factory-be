<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tồn đầu/cuối ngày) for ORDER count — DTG (PD only).
 *
 * Source: docs/rpt_factory_ops_metrics_v4.sql lines 710-744
 * Uses: dtg_item_detail → scan_label_history
 * Counts: COUNT(DISTINCT order_code)
 */
final class GetDtgOrderInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date): ?array
    {
        $sql = "
            WITH detail_item AS (
                SELECT
                    estimate_folder_date AS estimate_date,
                    order_code, index_num
                FROM dtg_item_detail
                WHERE estimate_folder_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND active = 1
            ),
            daily_aggregated AS (
                SELECT
                    estimate_date,
                    COUNT(DISTINCT IF(created_at IS NULL, order_code, NULL)) AS not_done,
                    COUNT(DISTINCT order_code) AS total_order
                FROM (
                    SELECT a.*, l.created_at
                    FROM detail_item a
                    LEFT JOIN scan_label_history l
                        ON a.order_code = l.barcode
                        AND l.created_at >= ? - INTERVAL 15 DAY
                        AND a.index_num = l.index_num
                    GROUP BY 1, 2, 3, 4
                ) b
                GROUP BY estimate_date
            )
            SELECT * FROM (
                SELECT
                    estimate_date,
                    total_order + COALESCE(SUM(not_done) OVER (
                        ORDER BY estimate_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS ton_dau,
                    SUM(not_done) OVER (ORDER BY estimate_date) AS ton_cuoi
                FROM daily_aggregated
            ) final_result
            WHERE estimate_date = ?
        ";

        return $this->formatResult($this->queryFplatform($sql, [$date, $date, $date, $date]));
    }
}
