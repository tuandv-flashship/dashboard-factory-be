<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng đơn / da_lam) for ORDER count — DTG (PD only).
 *
 * Source: FplatformData/sql/06_tong_don_theo_don.sql — DTG section (v4.0.0)
 *
 * Logic: target_items (dtg_folder_detail JOIN dtg_item_detail, with mark_time) →
 *        order_status (JOIN orders, get o.id) →
 *        item_scan_status (LEFT JOIN scan_label_history for first_scan).
 *        DON GUI LAI excluded via WHERE clause.
 *        Output: { estimate_date, tong_viec, da_lam }
 */
final class GetDtgOrderInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date): ?array
    {
        $sql = "
            WITH target_items AS (
                SELECT
                    fm.folder_key AS folder,
                    fm.scan_at AS mark_time,
                    fm.estimate_folder_date AS estimate_date,
                    d.order_code AS file_name_order_code,
                    d.index_num AS file_name_index_number,
                    IF(fm.folder_key LIKE 'REPRINT%', 'IN LAI', 'IN') AS status_folder
                FROM dtg_folder_detail fm
                JOIN dtg_item_detail d
                    ON d.folder_key = fm.folder_key
                    AND d.active = 1
                WHERE fm.estimate_folder_date BETWEEN ? - INTERVAL 10 DAY AND ?
                GROUP BY 1, 2, 3, 4, 5
            ),
            order_status AS (
                SELECT t.*, o.id
                FROM target_items t
                JOIN orders o ON o.order_code = t.file_name_order_code
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
            ),
            item_scan_status AS (
                SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.status_folder,
                    MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) AS first_scan
                FROM order_status fg
                LEFT JOIN scan_label_history s ON s.created_at >= fg.mark_time
                    AND s.order_id = fg.id AND s.index_num = fg.file_name_index_number
                GROUP BY 1, 2, 3, 4, 5
            )
            SELECT
                ? AS estimate_date,
                COUNT(DISTINCT CASE
                    WHEN DATE(first_scan) IS NULL OR DATE(first_scan) >= ?
                    THEN file_name_order_code
                END) AS tong_viec,
                COUNT(DISTINCT CASE
                    WHEN DATE(first_scan) = ?
                    THEN file_name_order_code
                END) AS da_lam
            FROM item_scan_status
            WHERE status_folder <> 'DON GUI LAI'
        ";

        return $this->formatOrderResult($this->queryFplatform($sql, [
            $date, $date, $date, $date, $date, $date, $date,
        ]));
    }
}
