<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hotshot mockup inventory (tổng việc & đã làm hotshot team mockup).
 *
 * Source: rpt_factory_ops_metrics_v8_1.sql lines 1985-2091
 * Filters by printer_default = MayHOTSHOT / MayHOTSHOTPD
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
            folder_printer AS (
                SELECT fm.estimate_date, fm.folder
                FROM folder_manage fm
                WHERE fm.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND fm.printer_default = ?
                    AND fm.status_folder <> 2
            ),
            a AS (
                SELECT
                    f.estimate_date,
                    f.folder,
                    d.file_name_order_code,
                    d.file_name_index_number,
                    COUNT(*) AS num_file
                FROM folder_printer f
                JOIN order_check_file_dropbox d
                    ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
                    AND d.status <> 2
                GROUP BY f.estimate_date, f.folder, d.file_name_order_code, d.file_name_index_number
            ),
            daily_aggregated AS (
                SELECT
                    estimate_date,
                    SUM(IF(created IS NULL, num_file, 0)) AS chua_lam,
                    SUM(num_file) AS total_file
                FROM (
                    SELECT a.*, l.created
                    FROM a
                    LEFT JOIN log_check_mockup l
                        ON a.file_name_order_code COLLATE utf8mb4_0900_ai_ci = l.barcode
                        AND l.created >= ? - INTERVAL 15 DAY
                        AND a.file_name_index_number = l.index_number
                    GROUP BY 1, 2, 3, 4
                ) b
                GROUP BY estimate_date
            )
            SELECT tong_viec, tong_viec - con_lai AS da_lam FROM (
                SELECT
                    estimate_date,
                    total_file + COALESCE(SUM(chua_lam) OVER (
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
