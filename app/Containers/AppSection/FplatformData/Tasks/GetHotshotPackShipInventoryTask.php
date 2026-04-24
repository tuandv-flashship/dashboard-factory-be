<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hotshot pack & ship inventory (tổng việc & đã làm — hotshot team pack & ship).
 *
 * Source: FplatformData/sql/26_hotshot_ao_pack_ship.sql (v2.0.0)
 *
 * Logic: target_folders (JOIN order_check_file_dropbox) → order_status (JOIN orders) →
 *        total_per_date / item_status / aggregated_status CTE.
 *        HOTSHOT printer uses strict date >= estimate_date cutoff (ngay_lam).
 *        total_per_date uses COUNT(*) from order_status.
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
            WITH
            target_folders AS (
                SELECT
                    fm.folder,
                    fm.estimate_date,
                    fm.printer_default,
                    d.file_name_order_code,
                    d.file_name_index_number
                FROM folder_manage fm
                JOIN order_check_file_dropbox d
                    ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
                    AND d.status <> 2
                WHERE fm.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND fm.printer_default = ?
                    AND fm.status_folder <> 2
                GROUP BY fm.estimate_date, fm.folder, fm.printer_default, d.file_name_order_code, d.file_name_index_number
            ),
            order_status AS (
                SELECT tf.*
                FROM target_folders tf
                JOIN orders o ON o.order_code = tf.file_name_order_code COLLATE utf8mb4_unicode_ci
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
                SELECT
                    fg.estimate_date,
                    CASE
                        WHEN fg.printer_default = ?
                        THEN MIN(CASE
                                WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= fg.estimate_date
                                THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'))
                             END)
                        ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
                    END AS ngay_lam
                FROM order_status fg
                LEFT JOIN scan_label_history s
                    ON s.barcode = fg.file_name_order_code COLLATE utf8mb4_0900_ai_ci
                    AND s.index_num = fg.file_name_index_number
                    AND s.created_at >= ? - INTERVAL 15 DAY
                GROUP BY fg.estimate_date, fg.folder, fg.printer_default, fg.file_name_order_code, fg.file_name_index_number
            ),
            aggregated_status AS (
                SELECT
                    estimate_date,
                    SUM(IF(ngay_lam < ?, 1, 0)) AS done_before,
                    SUM(IF(ngay_lam = ?, 1, 0)) AS done_today
                FROM item_status
                GROUP BY estimate_date
            )
            SELECT
                ? AS estimate_date,
                SUM(t.total_product - COALESCE(a.done_before, 0)) AS tong_viec,
                SUM(COALESCE(a.done_today, 0)) AS da_lam
            FROM total_per_date t
            LEFT JOIN aggregated_status a ON t.estimate_date = a.estimate_date
        ";

        $bindings = [$date, $date, $hotshotPrinter, $date, $date, $hotshotPrinter, $date, $date, $date, $date];

        return $this->formatHotshotResult($this->queryFplatform($sql, $bindings));
    }
}
