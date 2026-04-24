<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng đơn / da_lam) for ORDER count (DTF).
 *
 * Source: FplatformData/sql/06_tong_don_theo_don.sql (v2.0.0)
 *
 * Logic: target_items → order_status (JOIN orders) → item_status CTE.
 *        HOTSHOT/REPRINT printers use strict date >= estimate_date cutoff (ngay_lam).
 *        Output: { estimate_date, tong_viec, da_lam }
 */
final class GetOrderInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory): ?array
    {
        $extraUnions     = $this->buildExtraPrinterUnions($factory);
        $hotshotPrinters = $this->hotshotPrinterList($factory);

        $sql = "
            WITH
            target_items AS (
                SELECT
                    f.estimate_date,
                    f.folder,
                    f.printer_default,
                    d.file_name_order_code,
                    d.file_name_index_number
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
                GROUP BY f.estimate_date, f.folder, f.printer_default, d.file_name_order_code, d.file_name_index_number
            ),
            order_status AS (
                SELECT t.*
                FROM target_items t
                JOIN orders o ON o.order_code = t.file_name_order_code COLLATE utf8mb4_unicode_ci
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
            ),
            item_status AS (
                SELECT
                    ti.estimate_date,
                    ti.file_name_order_code,
                    CASE
                        WHEN ti.printer_default IN ({$hotshotPrinters}) THEN
                            MIN(CASE
                                WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= ti.estimate_date
                                THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'))
                            END)
                        ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
                    END AS ngay_lam
                FROM order_status ti
                LEFT JOIN scan_label_history s
                    ON s.barcode = ti.file_name_order_code COLLATE utf8mb4_0900_ai_ci
                    AND s.index_num = ti.file_name_index_number
                    AND s.created_at >= ? - INTERVAL 15 DAY
                GROUP BY ti.estimate_date, ti.folder, ti.printer_default, ti.file_name_order_code, ti.file_name_index_number
            )
            SELECT
                ? AS estimate_date,
                COUNT(DISTINCT IF(ngay_lam IS NULL OR ngay_lam >= ?, file_name_order_code, NULL)) AS tong_viec,
                COUNT(DISTINCT IF(ngay_lam = ?, file_name_order_code, NULL)) AS da_lam
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
