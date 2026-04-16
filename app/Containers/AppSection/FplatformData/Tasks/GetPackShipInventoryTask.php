<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng việc) for team Pack & Ship (DTF).
 *
 * Source: docs/rpt_factory_ops_metrics_v5.sql
 * Uses: folder_manage → order_check_file_dropbox → scan_label_history
 *
 * PD factory: UNION ALL with dtg_item_detail
 */
final class GetPackShipInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory): ?array
    {
        return $factory === FactoryLine::PD
            ? $this->runPdWithDtg($date, $factory)
            : $this->runDtfOnly($date, $factory);
    }

    /**
     * FLS: DTF data only (folder_manage → order_check_file_dropbox → scan_label_history)
     */
    private function runDtfOnly(string $date, FactoryLine $factory): ?array
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
                    SUM(IF(created_at IS NULL, num_shirt, 0)) AS not_done,
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
            SELECT * FROM (
                SELECT
                    estimate_date,
                    total_shirt + COALESCE(SUM(not_done) OVER (
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

    /**
     * PD: DTF + DTG union
     * Includes dtg_item_detail data via UNION ALL.
     */
    private function runPdWithDtg(string $date, FactoryLine $factory): ?array
    {
        $extraUnions = $this->buildExtraPrinterUnions($factory);

        $sql = "
            WITH
            target_printers AS (
                SELECT REPLACE(name, 'Machine ', 'May') COLLATE utf8mb4_unicode_ci AS printer_id
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
            dtf AS (
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
            dtg AS (
                SELECT estimate_folder_date, folder_key, order_code, index_num, COUNT(*) AS num_shirt
                FROM dtg_item_detail
                WHERE estimate_folder_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND active = 1
                GROUP BY 1, 2, 3, 4
            ),
            a AS (
                SELECT
                    estimate_date,
                    folder COLLATE utf8mb4_unicode_ci AS folder,
                    file_name_order_code COLLATE utf8mb4_unicode_ci AS order_code,
                    file_name_index_number AS index_num,
                    num_shirt
                FROM dtf
                UNION ALL
                SELECT
                    estimate_folder_date AS estimate_date,
                    folder_key COLLATE utf8mb4_unicode_ci AS folder,
                    order_code COLLATE utf8mb4_unicode_ci AS order_code,
                    index_num,
                    num_shirt
                FROM dtg
            ),
            daily_aggregated AS (
                SELECT
                    estimate_date,
                    SUM(IF(created_at IS NULL, num_shirt, 0)) AS not_done,
                    SUM(num_shirt) AS total_shirt
                FROM (
                    SELECT a.*, l.created_at
                    FROM a
                    LEFT JOIN scan_label_history l
                        ON a.order_code COLLATE utf8mb4_0900_ai_ci = l.barcode
                        AND l.created_at >= ? - INTERVAL 15 DAY
                        AND a.index_num = l.index_num
                    GROUP BY 1, 2, 3, 4
                ) b
                GROUP BY estimate_date
            )
            SELECT * FROM (
                SELECT
                    estimate_date,
                    total_shirt + COALESCE(SUM(not_done) OVER (
                        ORDER BY estimate_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS tong_viec
                FROM daily_aggregated
            ) final_result
            WHERE estimate_date = ?
        ";

        $bindings = array_merge(
            $this->printerBindings($factory),
            [$date, $date, $date, $date, $date, $date],
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }
}
