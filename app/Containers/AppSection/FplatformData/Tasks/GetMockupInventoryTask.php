<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tồn đầu/cuối ngày) for team Mockup (DTF).
 *
 * Source: docs/ton_dau_ngay_update.sql lines 154-272
 * Complex: folder_manage → order_check_file_dropbox → log_check_mockup
 */
final class GetMockupInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory): ?array
    {
        $extraUnions = $this->buildExtraPrinterUnions($factory);

        $sql = "
            WITH printer AS (
                SELECT REPLACE(NAME, 'Machine ', 'May') AS printer_name
                FROM printer_manage
                WHERE factory = ?
                {$extraUnions}
            ),
            a AS (
                SELECT
                    f.estimate_date,
                    f.folder,
                    d.file_name_order_code,
                    COUNT(*) AS num_file,
                    COALESCE(f.printer_share, f.printer_run, f.printer_default) AS printer
                FROM folder_manage f
                JOIN order_check_file_dropbox d
                    ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
                WHERE f.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND f.status_folder <> 2
                    AND d.status <> 2
                GROUP BY f.estimate_date, f.folder, d.file_name_order_code, printer
            ),
            filtered_a AS (
                SELECT a.* FROM a
                JOIN printer m_in ON a.printer = m_in.printer_name
            ),
            daily_aggregated AS (
                SELECT
                    estimate_date,
                    SUM(IF(created IS NULL, num_file, 0)) AS not_done,
                    SUM(num_file) AS total_file
                FROM (
                    SELECT
                        fa.estimate_date,
                        fa.num_file,
                        m.created,
                        ROW_NUMBER() OVER (
                            PARTITION BY fa.folder, fa.file_name_order_code
                            ORDER BY m.index_number DESC
                        ) AS rn
                    FROM filtered_a fa
                    LEFT JOIN log_check_mockup m
                        ON fa.file_name_order_code = m.barcode COLLATE utf8mb4_0900_ai_ci
                ) b
                WHERE rn = 1
                GROUP BY estimate_date
            )
            SELECT * FROM (
                SELECT
                    estimate_date,
                    total_file + COALESCE(SUM(not_done) OVER (
                        ORDER BY estimate_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS ton_dau,
                    SUM(not_done) OVER (ORDER BY estimate_date) AS ton_cuoi
                FROM daily_aggregated
            ) final_result
            WHERE estimate_date = ?
        ";

        $bindings = array_merge(
            $this->printerBindings($factory),
            [$date, $date, $date],
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }
}
