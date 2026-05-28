<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get order breakdown by estimate date (tổng đơn theo estimate — multi-row).
 *
 * Source: FplatformData/sql/28_tong_don_theo_estimate.sql (v5.0.0)
 *
 * Logic: Same as 06_tong_don_theo_don but GROUP BY estimate_date.
 *        Returns multiple rows: { estimate_date, tong_don, da_lam, chua_lam }
 *        Ordered by estimate_date DESC.
 */
final class GetOrderByEstimateTask extends ParentTask
{
    use QueriesFplatform;

    /**
     * DTF query — for FLS or PD factory.
     */
    public function runDtf(string $date, FactoryLine $factory): array
    {
        $extraUnions = $this->buildExtraPrinterUnions($factory);
        $hotshotPrinter = match ($factory) {
            FactoryLine::FLS => 'MayHOTSHOT',
            FactoryLine::PD  => 'MayHOTSHOTPD',
        };
        $reprintPrinter = match ($factory) {
            FactoryLine::FLS => 'MayREPRINT',
            FactoryLine::PD  => 'MayREPRINTPD',
        };

        $sql = "
            WITH target_folders_dtf AS (
                SELECT
                    fm.folder COLLATE utf8mb4_unicode_ci AS folder,
                    fm.created_at AS mark_time,
                    fm.estimate_date,
                    d.file_name_order_code COLLATE utf8mb4_unicode_ci AS file_name_order_code,
                    d.file_name_index_number,
                    CASE WHEN fm.printer_default = '{$hotshotPrinter}' AND fm.folder LIKE '%DON UU TIEN_DON GUI LAI%' THEN 'DON UU TIEN GUI LAI'
                         WHEN fm.printer_default = '{$hotshotPrinter}' AND fm.folder LIKE '%DON GUI LAI%' THEN 'DON GUI LAI'
                         WHEN fm.printer_default = '{$reprintPrinter}' THEN 'IN LAI'
                         ELSE 'IN' END AS status_folder
                FROM folder_manage fm
                JOIN order_check_file_dropbox d
                    ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
                    AND d.status <> 2
                WHERE fm.estimate_date BETWEEN ? - INTERVAL 9 DAY AND ?
                    AND fm.status_folder <> 2
                    AND COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
                        SELECT REPLACE(name, 'Machine ', 'May')
                        FROM printer_manage
                        WHERE factory = ?
                        {$extraUnions}
                    )
                GROUP BY fm.folder, fm.created_at, fm.estimate_date, d.file_name_order_code, d.file_name_index_number
            ),
            order_status AS (
                SELECT tf.*, o.id
                FROM target_folders_dtf tf
                JOIN orders o ON o.order_code = tf.file_name_order_code
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
            ),
            item_scan_status AS (
                SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.status_folder,
                    MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) AS firsr_scan
                FROM order_status fg
                LEFT JOIN scan_label_history s ON s.created_at >= fg.mark_time
                    AND s.order_id = fg.id AND s.index_num = fg.file_name_index_number
                GROUP BY 1, 2, 3, 4, 5
            ),
            order_summary AS (
                SELECT
                    file_name_order_code,
                    MAX(estimate_date) AS estimate_date,
                    COUNT(*) AS total_items,
                    COUNT(firsr_scan) AS scanned_items,
                    MAX(DATE(firsr_scan)) AS last_scan_date,
                    MIN(DATE(firsr_scan)) AS first_scan_date
                FROM item_scan_status
                WHERE status_folder <> 'DON GUI LAI'
                GROUP BY file_name_order_code
            )
            SELECT
                estimate_date,
                COUNT(DISTINCT CASE
                    WHEN first_scan_date IS NULL OR last_scan_date >= ?
                    THEN file_name_order_code
                END) AS tong_don,
                COUNT(DISTINCT CASE
                    WHEN total_items = scanned_items AND last_scan_date = ?
                    THEN file_name_order_code
                END) AS da_lam,
                COUNT(DISTINCT CASE
                    WHEN first_scan_date IS NULL
                    THEN file_name_order_code
                END) AS chua_lam
            FROM order_summary
            GROUP BY 1
            ORDER BY 1 DESC
        ";

        $bindings = array_merge(
            [$date, $date],
            $this->printerBindings($factory),
            [$date, $date, $date, $date],
        );

        return $this->formatEstimateResults($this->queryFplatformAll($sql, $bindings));
    }

    /**
     * DTG query — for PD factory only.
     */
    public function runDtg(string $date): array
    {
        $sql = "
            WITH target_items AS (
                SELECT
                    fm.folder_key AS folder,
                    fm.scan_at AS mark_time,
                    fm.estimate_folder_date AS estimate_date,
                    d.order_code AS file_name_order_code,
                    d.index_num AS file_name_index_number,
                    IF(fm.folder_key LIKE 'REPRINT%', 'IN LAI', 'IN') AS status_folder
                FROM dtg_folder_detail fm
                JOIN dtg_item_detail d
                    ON d.folder_key = fm.folder_key
                    AND d.active = 1
                WHERE fm.estimate_folder_date BETWEEN ? - INTERVAL 9 DAY AND ?
                GROUP BY 1, 2, 3, 4, 5
            ),
            order_status AS (
                SELECT t.*, o.id
                FROM target_items t
                JOIN orders o ON o.order_code = t.file_name_order_code
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
            ),
            item_scan_status AS (
                SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.status_folder,
                    MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) AS firsr_scan
                FROM order_status fg
                LEFT JOIN scan_label_history s ON s.created_at >= fg.mark_time
                    AND s.order_id = fg.id AND s.index_num = fg.file_name_index_number
                GROUP BY 1, 2, 3, 4, 5
            ),
            order_summary AS (
                SELECT
                    file_name_order_code,
                    MAX(estimate_date) AS estimate_date,
                    COUNT(*) AS total_items,
                    COUNT(firsr_scan) AS scanned_items,
                    MAX(DATE(firsr_scan)) AS last_scan_date,
                    MIN(DATE(firsr_scan)) AS first_scan_date
                FROM item_scan_status
                GROUP BY file_name_order_code
            )
            SELECT
                estimate_date,
                COUNT(DISTINCT CASE
                    WHEN first_scan_date IS NULL OR last_scan_date >= ?
                    THEN file_name_order_code
                END) AS tong_don,
                COUNT(DISTINCT CASE
                    WHEN total_items = scanned_items AND last_scan_date = ?
                    THEN file_name_order_code
                END) AS da_lam,
                COUNT(DISTINCT CASE
                    WHEN first_scan_date IS NULL
                    THEN file_name_order_code
                END) AS chua_lam
            FROM order_summary
            GROUP BY 1
            ORDER BY 1 DESC
        ";

        return $this->formatEstimateResults($this->queryFplatformAll($sql, [
            $date, $date, $date, $date, $date, $date,
        ]));
    }
}
