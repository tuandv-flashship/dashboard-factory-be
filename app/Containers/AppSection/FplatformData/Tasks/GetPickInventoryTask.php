<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng việc) for team Pick (DTF).
 *
 * Source: FplatformData/sql/02_tong_viec_team_pick.sql (v2.0.0)
 *
 * Logic: target_folders (JOIN order_check_file_dropbox) → order_status (JOIN orders) →
 *        LEFT JOIN user_group_scan (copy_job=0, created_at interval) → SUM(IF(..., 1, 0)).
 */
final class GetPickInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory): ?array
    {
        $extraUnions = $this->buildExtraPrinterUnions($factory);

        $sql = "
            WITH
            target_folders AS (
                SELECT
                    fm.folder,
                    fm.estimate_date,
                    d.file_name_order_code,
                    d.file_name_index_number
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
                GROUP BY fm.estimate_date, fm.folder, d.file_name_order_code, d.file_name_index_number
            ),
            order_status AS (
                SELECT tf.*
                FROM target_folders tf
                JOIN orders o ON o.order_code = tf.file_name_order_code COLLATE utf8mb4_unicode_ci
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
            )
            SELECT
                ? AS estimate_date,
                SUM(IF(
                    s.created_at IS NULL
                    OR s.created_at >= CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00'),
                    1, 0
                )) AS tong_viec
            FROM order_status o
            LEFT JOIN user_group_scan s ON s.folder = o.folder
                AND s.created_at > CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 12 DAY
                AND s.copy_job = 0
                AND s.work_type = 100
                AND s.work_status = 0
        ";

        $bindings = array_merge(
            [$date, $date],
            $this->printerBindings($factory),
            [$date, $date, $date, $date, $date],
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }
}
