<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hotshot order inventory (tổng đơn & đã làm — hotshot).
 *
 * Source: FplatformData/sql/16_so_don_hotshot.sql (v4.0.0)
 *
 * Logic: target_folders_dtf (with status_folder, mark_time) → order_status (JOIN orders, get o.id) →
 *        item_scan_status (LEFT JOIN scan_label_history for first_scan).
 *        DON GUI LAI excluded via WHERE clause.
 *        Output: { estimate_date, tong_viec, da_lam }
 */
final class GetHotshotOrderInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory): ?array
    {
        $hotshotPrinter = match ($factory) {
            FactoryLine::FLS => 'MayHOTSHOT',
            FactoryLine::PD  => 'MayHOTSHOTPD',
        };

        $sql = "
            WITH target_folders_dtf AS (
                SELECT
                    fm.folder COLLATE utf8mb4_unicode_ci AS folder,
                    fm.created_at AS mark_time,
                    fm.estimate_date,
                    d.file_name_order_code COLLATE utf8mb4_unicode_ci AS file_name_order_code,
                    d.file_name_index_number,
                    CASE WHEN fm.printer_default = '{$hotshotPrinter}' AND fm.folder LIKE '%DON UU TIEN_DON GUI LAI%' THEN 'DON UU TIEN GUI LAI'
                         WHEN fm.printer_default = '{$hotshotPrinter}' AND fm.folder LIKE '%DON GUI LAI%' THEN 'DON GUI LAI'
                         ELSE 'IN' END AS status_folder
                FROM folder_manage fm
                JOIN order_check_file_dropbox d
                    ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
                    AND d.status <> 2
                WHERE fm.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND fm.printer_default = ?
                    AND fm.status_folder <> 2
                GROUP BY fm.folder, fm.created_at, fm.estimate_date, d.file_name_order_code, d.file_name_index_number
            ),
            order_status AS (
                SELECT tf.*, o.id
                FROM target_folders_dtf tf
                JOIN orders o ON o.order_code = tf.file_name_order_code
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

        $bindings = [$date, $date, $hotshotPrinter, $date, $date, $date, $date, $date];

        return $this->formatOrderResult($this->queryFplatform($sql, $bindings));
    }
}
