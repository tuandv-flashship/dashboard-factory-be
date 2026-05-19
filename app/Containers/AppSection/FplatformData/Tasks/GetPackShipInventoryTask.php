<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng việc + đã làm) for team Pack & Ship (DTF).
 *
 * Source: FplatformData/sql/05_tong_viec_team_pack_ship.sql (v3.0.0)
 *
 * Logic: target_folders (JOIN order_check_file_dropbox) → order_status (JOIN orders, get o.id) →
 *        item_scan_status (JOIN scan_label_history via mark_time + order_id).
 *        Uses status_folder derived column (DON UU TIEN GUI LAI, DON GUI LAI, IN LAI, IN).
 *        PD factory includes DTG items (dtg_folder_detail + dtg_item_detail).
 *        Returns both tong_viec and da_lam.
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
        $extraUnions = $this->buildExtraPrinterUnions($factory);

        $sql = "
            WITH target_folders_dtf AS (
                SELECT
                    fm.folder COLLATE utf8mb4_unicode_ci AS folder,
                    fm.created_at AS mark_time,
                    fm.estimate_date,
                    d.file_name_order_code COLLATE utf8mb4_unicode_ci AS file_name_order_code,
                    d.file_name_index_number,
                    CASE WHEN fm.printer_default = 'MayHOTSHOT' AND fm.folder LIKE '%DON UU TIEN_DON GUI LAI%' THEN 'DON UU TIEN GUI LAI'
                         WHEN fm.printer_default = 'MayHOTSHOT' AND fm.folder LIKE '%DON GUI LAI%' THEN 'DON GUI LAI'
                         WHEN fm.printer_default = 'MayREPRINT' THEN 'IN LAI'
                         ELSE 'IN' END AS status_folder
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
            )
            SELECT
                ? AS estimate_date,
                COUNT(DISTINCT CASE
                    WHEN DATE(firsr_scan) IS NULL OR DATE(firsr_scan) >= ?
                    THEN CONCAT(file_name_order_code, file_name_index_number)
                END) AS tong_viec,
                COUNT(DISTINCT CASE
                    WHEN DATE(firsr_scan) = ?
                    THEN CONCAT(file_name_order_code, file_name_index_number)
                END) AS da_lam
            FROM item_scan_status
            WHERE status_folder <> 'DON GUI LAI'
        ";

        $bindings = array_merge(
            [$date, $date],
            $this->printerBindings($factory),
            [$date, $date, $date, $date, $date],
        );

        return $this->formatHotshotResult($this->queryFplatform($sql, $bindings));
    }

    /**
     * PD: DTF + DTG union.
     */
    private function runPdWithDtg(string $date, FactoryLine $factory): ?array
    {
        $extraUnions = $this->buildExtraPrinterUnions($factory);

        $sql = "
            WITH target_folders_dtf AS (
                SELECT
                    fm.folder COLLATE utf8mb4_unicode_ci AS folder,
                    fm.created_at AS mark_time,
                    fm.estimate_date,
                    d.file_name_order_code COLLATE utf8mb4_unicode_ci AS file_name_order_code,
                    d.file_name_index_number,
                    CASE WHEN fm.printer_default = 'MayHOTSHOTPD' AND fm.folder LIKE '%DON UU TIEN_DON GUI LAI%' THEN 'DON UU TIEN GUI LAI'
                         WHEN fm.printer_default = 'MayHOTSHOTPD' AND fm.folder LIKE '%DON GUI LAI%' THEN 'DON GUI LAI'
                         WHEN fm.printer_default = 'MayREPRINTPD' THEN 'IN LAI'
                         ELSE 'IN' END AS status_folder
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
                GROUP BY fm.folder, fm.created_at, fm.estimate_date, d.file_name_order_code, d.file_name_index_number
            ),
            target_folders_dtg AS (
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
                WHERE fm.estimate_folder_date BETWEEN ? - INTERVAL 10 DAY AND ?
                GROUP BY 1, 2, 3, 4, 5
            ),
            printdash AS (
                SELECT * FROM target_folders_dtf
                UNION ALL
                SELECT * FROM target_folders_dtg
            ),
            order_status AS (
                SELECT tf.*, o.id
                FROM printdash tf
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
            )
            SELECT
                ? AS estimate_date,
                COUNT(DISTINCT CASE
                    WHEN DATE(firsr_scan) IS NULL OR DATE(firsr_scan) >= ?
                    THEN CONCAT(file_name_order_code, file_name_index_number)
                END) AS tong_viec,
                COUNT(DISTINCT CASE
                    WHEN DATE(firsr_scan) = ?
                    THEN CONCAT(file_name_order_code, file_name_index_number)
                END) AS da_lam
            FROM item_scan_status
            WHERE status_folder <> 'DON GUI LAI'
        ";

        $bindings = array_merge(
            [$date, $date],
            $this->printerBindings($factory),
            [$date, $date, $date, $date, $date, $date, $date],
        );

        return $this->formatHotshotResult($this->queryFplatform($sql, $bindings));
    }
}
