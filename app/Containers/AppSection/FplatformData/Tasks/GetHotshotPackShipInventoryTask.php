<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hotshot pack & ship inventory (tổng việc & đã làm hotshot team pack & ship).
 *
 * Source: rpt_factory_ops_metrics_v8_1.sql lines 2094-2196
 * Filters by printer_default = MayHOTSHOT / MayHOTSHOTPD
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
                    SUM(IF(created_at IS NULL, num_shirt, 0)) AS chua_lam,
                    SUM(num_shirt) AS total_shirt
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
            SELECT tong_viec, tong_viec - con_lai AS da_lam FROM (
                SELECT
                    estimate_date,
                    total_shirt + COALESCE(SUM(chua_lam) OVER (
                        ORDER BY estimate_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS tong_viec,
                    SUM(chua_lam) OVER (ORDER BY estimate_date) AS con_lai
                FROM daily_aggregated
            ) result
            WHERE estimate_date = ?
        ";

        $bindings = [$date, $date, $hotshotPrinter, $date, $date];

        return $this->formatHotshotResult($this->queryFplatform($sql, $bindings));
    }

    private function formatHotshotResult(?object $result): ?array
    {
        if (!$result) {
            return null;
        }

        return [
            'tong_viec' => (int) $result->tong_viec,
            'da_lam'    => (int) $result->da_lam,
        ];
    }
}
