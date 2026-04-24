<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hotshot mockup inventory (tổng việc & đã làm — hotshot team mockup).
 *
 * Source: FplatformData/sql/25_hotshot_file_team_mockup.sql (v2.0.0)
 *
 * Logic: target_folders (JOIN order_check_file_dropbox) → order_status (JOIN orders) →
 *        calc_total / file_status CTE.
 *        HOTSHOT printer uses strict date >= estimate_date cutoff (ngay_lam).
 *        calc_total uses SUM(num_file) from order_status.
 */
final class GetHotshotMockupInventoryTask extends ParentTask
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
                    d.file_name_index_number,
                    COUNT(*) AS num_file
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
            calc_total AS (
                SELECT SUM(num_file) AS sum_total_file
                FROM order_status
            ),
            file_status AS (
                SELECT
                    fg.num_file,
                    CASE
                        WHEN fg.printer_default = ?
                        THEN MIN(CASE
                                WHEN DATE(CONVERT_TZ(l.created, '+7:00', 'US/Central')) >= fg.estimate_date
                                THEN DATE(CONVERT_TZ(l.created, '+7:00', 'US/Central'))
                             END)
                        ELSE DATE(MIN(CONVERT_TZ(l.created, '+7:00', 'US/Central')))
                    END AS ngay_lam
                FROM order_status fg
                LEFT JOIN log_check_mockup l
                    ON l.barcode = fg.file_name_order_code COLLATE utf8mb4_0900_ai_ci
                    AND l.index_number = fg.file_name_index_number
                    AND l.created >= ? - INTERVAL 15 DAY
                GROUP BY fg.estimate_date, fg.printer_default, fg.file_name_order_code, fg.file_name_index_number, fg.num_file
            )
            SELECT
                ? AS estimate_date,
                COALESCE((SELECT sum_total_file FROM calc_total), 0)
                    - COALESCE((SELECT SUM(num_file) FROM file_status WHERE ngay_lam < ?), 0) AS tong_viec,
                COALESCE((SELECT SUM(num_file) FROM file_status WHERE ngay_lam = ?), 0) AS da_lam
        ";

        $bindings = [$date, $date, $hotshotPrinter, $date, $date, $hotshotPrinter, $date, $date, $date, $date];

        return $this->formatHotshotResult($this->queryFplatform($sql, $bindings));
    }
}
