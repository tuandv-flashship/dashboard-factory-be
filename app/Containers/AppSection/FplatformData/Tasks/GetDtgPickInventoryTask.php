<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng việc) for team Pick - DTG.
 *
 * Source: docs/rpt_factory_ops_metrics_v5.sql
 * Uses: dtg_folder_detail + dtg_item_detail (no factory param)
 */
final class GetDtgPickInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date): ?array
    {
        $sql = "
            WITH daily_summary AS (
                SELECT
                    f.estimate_folder_date,
                    COUNT(d.folder_key) AS total_shirt,
                    SUM(IF(f.done_at IS NULL, 1, 0)) AS chua_pick
                FROM dtg_folder_detail f
                INNER JOIN dtg_item_detail d ON d.folder_key = f.folder_key
                WHERE f.estimate_folder_date BETWEEN ? - INTERVAL 10 DAY AND ?
                GROUP BY f.estimate_folder_date
            )
            SELECT estimate_folder_date AS estimate_date,
                tong_viec
            FROM (
                SELECT
                    estimate_folder_date,
                    total_shirt + COALESCE(SUM(chua_pick) OVER (
                        ORDER BY estimate_folder_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS tong_viec
                FROM daily_summary
            ) result
            WHERE estimate_folder_date = ?
        ";

        return $this->formatResult($this->queryFplatform($sql, [$date, $date, $date]));
    }
}
