<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng việc) for team IN - DTG.
 *
 * Source: FplatformData/sql/01_tong_viec_team_in.sql — DTG section (v2.0.0)
 *
 * Logic: item_detail (JOIN orders ON o.id = d.order_id) →
 *        LEFT JOIN dtg_printed_product → SUM(IF(...))
 */
final class GetDtgPrintInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date): ?array
    {
        $sql = "
            WITH item_detail AS (
                SELECT
                    d.estimate_folder_date,
                    d.folder_key,
                    d.order_code,
                    d.distribute_id,
                    d.index_num
                FROM dtg_item_detail d
                JOIN orders o ON o.id = d.order_id
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
                WHERE d.estimate_folder_date BETWEEN ? - INTERVAL 9 DAY AND ?
                    AND d.active = 1
                GROUP BY d.estimate_folder_date, d.folder_key, d.order_code, d.distribute_id, d.index_num
            )
            SELECT
                ? AS estimate_date,
                SUM(IF(p.printed_at IS NULL OR p.printed_at >= CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00'), 1, 0)) AS tong_viec
            FROM item_detail i
            LEFT JOIN dtg_printed_product p
                ON i.order_code = p.order_code
                AND i.index_num = p.index_num
                AND i.distribute_id = p.distribute_id
        ";

        return $this->formatResult($this->queryFplatform($sql, [$date, $date, $date, $date, $date, $date]));
    }
}
