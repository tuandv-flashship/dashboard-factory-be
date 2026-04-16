<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hotshot order inventory (tổng việc & đã làm đơn hotshot).
 *
 * Source: docs/rpt_factory_ops_metrics_v5.sql
 * Filters by printer_default = MayHOTSHOT / MayHOTSHOTPD
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
            WITH
            folder_printer AS (
                SELECT fm.estimate_date, fm.folder
                FROM folder_manage fm
                WHERE fm.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND fm.printer_default = ?
                    AND fm.status_folder <> 2
            ),
            a AS (
                SELECT *, COUNT(*) AS num_shirt FROM (
                    SELECT
                        f.estimate_date,
                        f.folder,
                        d.file_name_order_code,
                        d.file_name_index_number
                    FROM order_check_file_dropbox d
                    JOIN folder_printer f
                        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
                        AND d.status <> 2
                    GROUP BY f.estimate_date, f.folder, d.file_name_order_code, d.file_name_index_number
                ) c
                GROUP BY estimate_date, folder, file_name_order_code, file_name_index_number
            ),
            daily_aggregated AS (
                SELECT
                    estimate_date,
                    COUNT(DISTINCT IF(created_at IS NULL, file_name_order_code, NULL)) AS not_done,
                    COUNT(DISTINCT file_name_order_code) AS total_order
                FROM (
                    SELECT a.*, l.created_at
                    FROM a
                    LEFT JOIN scan_label_history l
                        ON a.file_name_order_code COLLATE utf8mb4_0900_ai_ci = l.barcode
                        AND l.created_at >= ? - INTERVAL 15 DAY
                        AND a.file_name_index_number = l.index_num
                    GROUP BY 1, 2, 3, 4
                ) b
                GROUP BY estimate_date
            )
            SELECT * FROM (
                SELECT
                    estimate_date,
                    total_order + COALESCE(SUM(not_done) OVER (
                        ORDER BY estimate_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS tong_viec,
                    SUM(not_done) OVER (ORDER BY estimate_date) AS da_lam
                FROM daily_aggregated
            ) final_result
            WHERE estimate_date = ?
        ";

        return $this->formatOrderResult($this->queryFplatform($sql, [
            $date, $date, $hotshotPrinter, $date, $date,
        ]));
    }
}
