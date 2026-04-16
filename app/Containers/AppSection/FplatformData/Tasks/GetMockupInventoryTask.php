<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng việc) for team Mockup (DTF).
 *
 * Source: docs/rpt_factory_ops_metrics_v5.sql
 * Complex: folder_manage → order_check_file_dropbox → log_check_mockup
 */
final class GetMockupInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory): ?array
    {
        $extraUnions = $this->buildExtraPrinterUnions($factory);

        $sql = "
            WITH
            target_printers AS (
                SELECT REPLACE(name, 'Machine ', 'May') AS printer_id
                FROM printer_manage
                WHERE factory = ?
                {$extraUnions}
            ),
            folder_printer AS (
                SELECT fm.estimate_date, fm.folder
                FROM folder_manage fm
                JOIN target_printers p
                    ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
                WHERE fm.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
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
                    SUM(IF(created IS NULL, num_file, 0)) AS not_done,
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
            SELECT * FROM (
                SELECT
                    estimate_date,
                    total_file + COALESCE(SUM(not_done) OVER (
                        ORDER BY estimate_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS tong_viec
                FROM daily_aggregated
            ) final_result
            WHERE estimate_date = ?
        ";

        $bindings = array_merge(
            $this->printerBindings($factory),
            [$date, $date, $date, $date],
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }
}
