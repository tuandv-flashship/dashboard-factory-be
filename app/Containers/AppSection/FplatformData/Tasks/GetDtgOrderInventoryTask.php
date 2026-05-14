<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng đơn / da_lam) for ORDER count — DTG (PD only).
 *
 * Source: FplatformData/sql/06_tong_don_theo_don.sql — DTG section (v2.0.0)
 *
 * Logic: target_items → order_status (JOIN orders) → item_status CTE via scan_label_history.
 *        Output: { estimate_date, tong_don, da_lam }
 */
final class GetDtgOrderInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date): ?array
    {
        $sql = "
            WITH
            target_items AS (
                SELECT
                    estimate_folder_date AS estimate_date,
                    folder_key AS folder,
                    IF(folder_key LIKE 'REPRINT%', 'REPRINT', NULL) AS printer_default,
                    order_code AS file_name_order_code,
                    index_num AS file_name_index_number
                FROM dtg_item_detail
                WHERE estimate_folder_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND active = 1
                GROUP BY estimate_folder_date, folder_key, order_code, index_num
            ),
            order_status AS (
                SELECT t.*
                FROM target_items t
                JOIN orders o ON o.order_code = t.file_name_order_code COLLATE utf8mb4_unicode_ci
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
            ),
            item_status AS (
                SELECT
                    ti.estimate_date,
                    ti.file_name_order_code,
                    CASE
                        WHEN ti.printer_default = 'REPRINT' THEN
                            MIN(CASE
                                WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= ti.estimate_date
                                THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'))
                            END)
                        ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
                    END AS ngay_lam
                FROM order_status ti
                LEFT JOIN scan_label_history s
                    ON s.barcode = ti.file_name_order_code
                    AND s.index_num = ti.file_name_index_number
                    AND s.created_at >= ? - INTERVAL 15 DAY
                GROUP BY ti.estimate_date, ti.folder, ti.printer_default, ti.file_name_order_code, ti.file_name_index_number
            )
            SELECT
                ? AS estimate_date,
                (
                    SELECT COALESCE(SUM(c), 0)
                    FROM (
                        SELECT COUNT(DISTINCT IF(ngay_lam IS NULL OR ngay_lam >= ?, file_name_order_code, NULL)) AS c
                        FROM item_status
                        GROUP BY estimate_date
                    ) sub
                ) AS tong_don,
                (
                    SELECT COUNT(DISTINCT IF(ngay_lam = ?, file_name_order_code, NULL))
                    FROM item_status
                ) AS da_lam
        ";

        return $this->formatOrderResult($this->queryFplatform($sql, [$date, $date, $date, $date, $date, $date, $date, $date]));
    }
}
