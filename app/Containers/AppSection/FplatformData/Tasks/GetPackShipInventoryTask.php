<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng việc) for team Pack & Ship (DTF).
 *
 * Source: FplatformData/sql/05_tong_viec_team_pack_ship.sql (v2.0.0)
 *
 * Logic: target_folders (JOIN order_check_file_dropbox) → order_status (JOIN orders) →
 *        total_per_date / done_filtered / done_per_date CTE.
 *        HOTSHOT/REPRINT printers use strict date >= estimate_date cutoff.
 *        PD factory includes DTG items (dtg_folder_detail + dtg_item_detail).
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
     * FLS: DTF data only.
     */
    private function runDtfOnly(string $date, FactoryLine $factory): ?array
    {
        $extraUnions     = $this->buildExtraPrinterUnions($factory);
        $hotshotPrinters = $this->hotshotPrinterList($factory);

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
                    AND fm.status_folder <> 2
                    AND COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
                        SELECT REPLACE(name, 'Machine ', 'May')
                        FROM printer_manage
                        WHERE factory = ?
                        {$extraUnions}
                    )
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
            done_filtered AS (
                SELECT fg.estimate_date
                FROM order_status fg
                LEFT JOIN scan_label_history s
                    ON s.barcode = fg.file_name_order_code COLLATE utf8mb4_0900_ai_ci
                    AND s.index_num = fg.file_name_index_number
                    AND s.created_at >= ? - INTERVAL 15 DAY
                GROUP BY fg.estimate_date, fg.folder, fg.printer_default, fg.file_name_order_code, fg.file_name_index_number
                HAVING
                    CASE
                        WHEN fg.printer_default IN ({$hotshotPrinters}) THEN
                            MIN(CASE
                                WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= fg.estimate_date
                                THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'))
                            END)
                        ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
                    END < ?
            ),
            done_per_date AS (
                SELECT estimate_date, COUNT(*) AS da_lam
                FROM done_filtered
                GROUP BY estimate_date
            )
            SELECT
                ? AS estimate_date,
                SUM(t.total_product - COALESCE(d.da_lam, 0)) AS tong_viec
            FROM total_per_date t
            LEFT JOIN done_per_date d ON t.estimate_date = d.estimate_date
        ";

        $bindings = array_merge(
            [$date, $date],
            $this->printerBindings($factory),
            [$date, $date, $date, $date, $date],
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }

    /**
     * PD: DTF + DTG union.
     */
    private function runPdWithDtg(string $date, FactoryLine $factory): ?array
    {
        $extraUnions     = $this->buildExtraPrinterUnions($factory);
        $hotshotPrinters = $this->hotshotPrinterList($factory);

        $sql = "
            WITH
            target_folders_dtf AS (
                SELECT
                    fm.folder COLLATE utf8mb4_unicode_ci AS folder,
                    fm.estimate_date,
                    fm.printer_default,
                    d.file_name_order_code COLLATE utf8mb4_unicode_ci AS file_name_order_code,
                    d.file_name_index_number
                FROM folder_manage fm
                JOIN order_check_file_dropbox d
                    ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
                    AND d.status <> 2
                WHERE fm.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND fm.status_folder <> 2
                    AND COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
                        SELECT REPLACE(name, 'Machine ', 'May')
                        FROM printer_manage
                        WHERE factory = ?
                        {$extraUnions}
                    )
                GROUP BY fm.estimate_date, fm.folder, fm.printer_default, d.file_name_order_code, d.file_name_index_number
            ),
            target_folders_dtg AS (
                SELECT
                    fm.folder_key AS folder,
                    fm.estimate_folder_date AS estimate_date,
                    IF(fm.folder_key LIKE 'REPRINT%', 'REPRINT', NULL) AS printer_default,
                    d.order_code AS file_name_order_code,
                    d.index_num AS file_name_index_number
                FROM dtg_folder_detail fm
                JOIN dtg_item_detail d
                    ON d.folder_key = fm.folder_key
                    AND d.active = 1
                WHERE fm.estimate_folder_date BETWEEN ? - INTERVAL 10 DAY AND ?
                GROUP BY 1, 2, 3, 4, 5
            ),
            printdash AS (
                SELECT * FROM target_folders_dtf
                UNION ALL
                SELECT * FROM target_folders_dtg
            ),
            order_status AS (
                SELECT tf.*
                FROM printdash tf
                JOIN orders o ON o.order_code = tf.file_name_order_code COLLATE utf8mb4_unicode_ci
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
            ),
            item_scan_status AS (
                SELECT
                    CASE
                        WHEN fg.printer_default IN ({$hotshotPrinters}) THEN
                            MIN(CASE
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
            )
            SELECT
                ? AS estimate_date,
                SUM(IF(ngay_lam IS NULL OR ngay_lam >= ?, 1, 0)) AS tong_viec
            FROM item_scan_status
        ";

        $bindings = array_merge(
            [$date, $date],
            $this->printerBindings($factory),
            [$date, $date, $date, $date, $date, $date, $date],
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }
}
