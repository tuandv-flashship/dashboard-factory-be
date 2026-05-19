<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng đơn / da_lam) for ORDER count — DTG (PD only).
 *
 * Source: FplatformData/sql/06_tong_don_theo_don.sql — DTG section (v3.0.0)
 *
 * Logic: target_items (with folder_status) → order_status (JOIN orders, get o.id) →
 *        item_status (JOIN report.report_orders for first_get/last_get).
 *        Output: { estimate_date, tong_don, da_lam }
 */
final class GetDtgOrderInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date): ?array
    {
        $sql = "
            WITH target_items AS (
                SELECT
                    estimate_folder_date AS estimate_date,
                    folder_key AS folder,
                    IF(folder_key LIKE 'REPRINT%', 'REPRINT', 'IN') AS folder_status,
                    order_code AS file_name_order_code,
                    index_num AS file_name_index_number
                FROM dtg_item_detail
                WHERE estimate_folder_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND active = 1
                GROUP BY estimate_folder_date, folder_key, order_code, index_num
            ),
            order_status AS (
                SELECT t.*, o.id
                FROM target_items t
                JOIN orders o ON o.order_code = t.file_name_order_code
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
            ),
            item_status AS (
                SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.folder_status,
                    DATE(CONVERT_TZ(r.first_get_label_at, '+7:00', 'US/Central')) AS first_get,
                    DATE(CONVERT_TZ(r.last_get_label_at, '+7:00', 'US/Central')) AS last_get
                FROM order_status fg
                LEFT JOIN report.report_orders r ON r.id = fg.id
            )
            SELECT
                ? AS estimate_date,
                COUNT(DISTINCT CASE
                    WHEN last_get IS NULL OR last_get >= ?
                    THEN file_name_order_code
                END) AS tong_don,
                COUNT(DISTINCT CASE
                    WHEN last_get = ?
                    THEN file_name_order_code
                END) AS da_lam
            FROM item_status
        ";

        return $this->formatOrderResult($this->queryFplatform($sql, [
            $date, $date, $date, $date, $date, $date, $date,
        ]));
    }
}
