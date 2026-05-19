<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hotshot pack & ship inventory (tổng việc & đã làm — hotshot team pack & ship).
 *
 * Source: FplatformData/sql/26_hotshot_ao_pack_ship.sql (v3.0.0)
 *
 * Logic: target_folders (with folder_status) → order_status (JOIN orders, get o.id) →
 *        total_per_date → item_status (JOIN report.report_orders for first_get/last_get).
 *        DON GUI LAI excluded from final count.
 *        Output: { estimate_date, tong_viec, da_lam }
 */
final class GetHotshotPackShipInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory): ?array
    {
        $hotshotPrinter = match ($factory) {
            FactoryLine::FLS => 'MayHOTSHOT',
            FactoryLine::PD  => 'MayHOTSHOTPD',
        };

        $sql = "
            WITH target_folders AS (
                SELECT
                    fm.folder,
                    fm.estimate_date,
                    d.file_name_order_code,
                    d.file_name_index_number,
                    CASE WHEN fm.printer_default = '{$hotshotPrinter}' AND fm.folder LIKE '%DON UU TIEN_DON GUI LAI%' THEN 'DON UU TIEN GUI LAI'
                         WHEN fm.printer_default = '{$hotshotPrinter}' AND fm.folder LIKE '%DON GUI LAI%' THEN 'DON GUI LAI'
                         ELSE 'IN' END AS folder_status
                FROM folder_manage fm
                JOIN order_check_file_dropbox d
                    ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
                    AND d.status <> 2
                WHERE fm.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND fm.status_folder <> 2
                    AND fm.printer_default = ?
                GROUP BY fm.estimate_date, fm.folder, d.file_name_order_code, d.file_name_index_number
            ),
            order_status AS (
                SELECT tf.*, o.id
                FROM target_folders tf
                JOIN orders o ON o.order_code = tf.file_name_order_code
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
            ),
            total_per_date AS (
                SELECT estimate_date, COUNT(*) AS total_product
                FROM order_status
                GROUP BY estimate_date
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
                SUM(CASE
                    WHEN last_get IS NULL OR last_get >= ?
                    THEN 1
                END) AS tong_viec,
                SUM(CASE
                    WHEN last_get = ?
                    THEN 1
                END) AS da_lam
            FROM item_status
            WHERE folder_status <> 'DON GUI LAI'
        ";

        $bindings = [$date, $date, $hotshotPrinter, $date, $date, $date, $date, $date];

        return $this->formatHotshotResult($this->queryFplatform($sql, $bindings));
    }
}
