<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng đơn / da_lam) for ORDER count (DTF).
 *
 * Source: FplatformData/sql/06_tong_don_theo_don.sql (v3.0.0)
 *
 * Logic: target_items (with folder_status) → order_status (JOIN orders, get o.id) →
 *        item_status (JOIN report.report_orders for first_get/last_get).
 *        DON GUI LAI has special handling in da_lam count.
 *        Output: { estimate_date, tong_don, da_lam }
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
            WITH target_items AS (
                SELECT
                    f.estimate_date,
                    f.folder,
                    d.file_name_order_code,
                    d.file_name_index_number,
                    CASE WHEN f.printer_default = '{$hotshotPrinter}' AND f.folder LIKE '%DON UU TIEN_DON GUI LAI%' THEN 'DON UU TIEN GUI LAI'
                         WHEN f.printer_default = '{$hotshotPrinter}' AND f.folder LIKE '%DON GUI LAI%' THEN 'DON GUI LAI'
                         WHEN f.printer_default = '{$reprintPrinter}' THEN 'IN LAI'
                         ELSE 'IN' END AS folder_status
                FROM folder_manage f
                JOIN order_check_file_dropbox d
                    ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
                    AND d.status <> 2
                WHERE f.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND f.status_folder <> 2
                    AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
                        SELECT REPLACE(name, 'Machine ', 'May')
                        FROM printer_manage
                        WHERE factory = ?
                        {$extraUnions}
                    )
                GROUP BY f.estimate_date, f.folder, d.file_name_order_code, d.file_name_index_number
            ),
            order_status AS (
                SELECT t.*, o.id
                FROM target_items t
                JOIN orders o ON o.order_code = t.file_name_order_code
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
            ),
            item_status AS (
                SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, fg.folder_status,
                    DATE(CONVERT_TZ(r.first_get_label_at, '+7:00', 'US/Central')) AS first_get,
                    DATE(CONVERT_TZ(r.last_get_label_at, '+7:00', 'US/Central')) AS last_get
                FROM order_status fg
                LEFT JOIN report.report_orders r ON r.id = fg.id
            )
            SELECT
                ? AS estimate_date,
                COUNT(DISTINCT CASE
                    WHEN folder_status <> 'DON GUI LAI'
                         AND (last_get IS NULL OR last_get >= ?)
                    THEN file_name_order_code
                END) AS tong_don,
                COUNT(DISTINCT CASE
                    WHEN last_get = ? OR (estimate_date = ? AND folder_status = 'DON GUI LAI')
                    THEN file_name_order_code
                END) AS da_lam
            FROM item_status
        ";

        $bindings = array_merge(
            [$date, $date],
            $this->printerBindings($factory),
            [$date, $date, $date, $date, $date, $date],
        );

        return $this->formatOrderResult($this->queryFplatform($sql, $bindings));
    }
}
