<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng đơn / da_lam) for ORDER count (DTF).
 *
 * Source: FplatformData/sql/06_tong_don_theo_don.sql (v4.0.0)
 *
 * Logic: target_folders_dtf (with status_folder, mark_time) → order_status (JOIN orders, get o.id) →
 *        item_scan_status (LEFT JOIN scan_label_history for first_scan).
 *        DON GUI LAI excluded via WHERE clause.
 *        Output: { estimate_date, tong_viec, da_lam }
 */
final class GetOrderInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory): ?array
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
                SELECT t.*, o.id
                FROM target_folders_dtf t
                JOIN orders o ON o.order_code = t.file_name_order_code
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
            ),
            item_scan_status AS (
                SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.status_folder,
                    MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) AS first_scan
                FROM order_status fg
                LEFT JOIN scan_label_history s ON s.created_at >= fg.mark_time
                    AND s.order_id = fg.id AND s.index_num = fg.file_name_index_number
                GROUP BY 1, 2, 3, 4, 5
            )
            SELECT
                ? AS estimate_date,
                COUNT(DISTINCT CASE
                    WHEN DATE(first_scan) IS NULL OR DATE(first_scan) >= ?
                    THEN file_name_order_code
                END) AS tong_viec,
                COUNT(DISTINCT CASE
                    WHEN DATE(first_scan) = ?
                    THEN file_name_order_code
                END) AS da_lam
            FROM item_scan_status
            WHERE status_folder <> 'DON GUI LAI'
        ";

        $bindings = array_merge(
            [$date, $date],
            $this->printerBindings($factory),
            [$date, $date, $date, $date, $date],
        );

        return $this->formatOrderResult($this->queryFplatform($sql, $bindings));
    }
}
