<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tồn đầu/cuối ngày) for team Pack & Ship (DTF).
 *
 * Source: docs/ton_dau_ngay_update.sql lines 282-396
 * Uses: folder_manage → order_check_file_dropbox → scan_label_history
 */
final class GetPackShipInventoryTask extends ParentTask
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
                    f.date,
                    f.estimate_date,
                    f.folder,
                    d.file_name_order_code,
                    COUNT(DISTINCT d.file_name_index_number) AS num_file
                FROM folder_manage f
                JOIN order_check_file_dropbox d
                    ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
                JOIN printer tp
                    ON tp.printer_name = COALESCE(f.printer_share, f.printer_run, f.printer_default)
                WHERE f.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND f.status_folder <> 2
                    AND d.status <> 2
                GROUP BY f.date, f.estimate_date, f.folder, d.file_name_order_code
            ),
            daily_aggregated AS (
                SELECT
                    estimate_date,
                    SUM(IF(created_at IS NULL, num_file, 0)) AS shirt_chua_scan,
                    SUM(num_file) AS total_shirt
                FROM (
                    SELECT
                        a.estimate_date,
                        a.num_file,
                        m.created_at,
                        ROW_NUMBER() OVER (
                            PARTITION BY a.folder, a.file_name_order_code
                            ORDER BY m.id DESC
                        ) AS rn
                    FROM a
                    LEFT JOIN scan_label_history m
                        ON a.file_name_order_code = m.barcode COLLATE utf8mb4_0900_ai_ci
                ) b
                WHERE rn = 1
                GROUP BY estimate_date
            )
            SELECT * FROM (
                SELECT
                    estimate_date,
                    total_shirt + COALESCE(SUM(shirt_chua_scan) OVER (
                        ORDER BY estimate_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS ton_dau,
                    SUM(shirt_chua_scan) OVER (ORDER BY estimate_date) AS ton_cuoi
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
