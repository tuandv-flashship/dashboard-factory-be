<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (chua_lam / da_lam) for ORDER count — DTG (PD only).
 *
 * Source: FplatformData/sql/06_tong_don_theo_don.sql — DTG section (v1.1.0)
 *
 * Logic: target_items → item_status CTE via scan_label_history.
 *        Output: { estimate_date, chua_lam, da_lam }
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
                    order_code AS file_name_order_code,
                    index_num AS file_name_index_number
                FROM dtg_item_detail
                WHERE estimate_folder_date BETWEEN ? - INTERVAL 10 DAY AND ?
                GROUP BY estimate_folder_date, folder_key, order_code, index_num
            ),
            item_status AS (
                SELECT
                    ti.file_name_order_code,
                    DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'))) AS ngay_lam
                FROM target_items ti
                LEFT JOIN scan_label_history s
                    ON s.barcode = ti.file_name_order_code
                    AND s.index_num = ti.file_name_index_number
                    AND s.created_at >= ? - INTERVAL 15 DAY
                GROUP BY ti.estimate_date, ti.folder, ti.file_name_order_code, ti.file_name_index_number
            )
            SELECT
                ? AS estimate_date,
                COUNT(DISTINCT IF(ngay_lam IS NULL OR ngay_lam >= ?, file_name_order_code, NULL)) AS tong_viec,
                COUNT(DISTINCT IF(ngay_lam = ?, file_name_order_code, NULL)) AS da_lam
            FROM item_status
        ";

        return $this->formatOrderResult($this->queryFplatform($sql, [$date, $date, $date, $date, $date, $date]));
    }
}
