<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng việc) for team Mockup (DTF).
 *
 * Source: FplatformData/sql/04_tong_viec_team_mockup.sql (v5.0.0)
 *
 * Logic: target_folders (JOIN order_check_file_dropbox, with mark_time) →
 *        order_status (JOIN orders, get o.id) →
 *        item_scan_status (LEFT JOIN log_check_mockup via mark_time + order_id + index_number) →
 *        SUM(CASE WHEN date(firsr_scan) IS NULL OR >= estimate_date THEN num_file END).
 */
final class GetMockupInventoryTask extends ParentTask
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
                    fm.created_at AS mark_time,
                    d.file_name_order_code,
                    d.file_name_index_number,
                    COUNT(*) AS num_file
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
                GROUP BY fm.estimate_date, fm.created_at, fm.folder, d.file_name_order_code, d.file_name_index_number
            ),
            order_status AS (
                SELECT tf.*, o.id
                FROM target_folders tf
                JOIN orders o ON o.order_code = tf.file_name_order_code COLLATE utf8mb4_unicode_ci
                    AND o.created BETWEEN CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 24 DAY
                                       AND CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00')
                    AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
            ),
            item_scan_status AS (
                SELECT fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, num_file,
                    MIN(CONVERT_TZ(s.created, '+7:00', 'US/Central')) AS firsr_scan
                FROM order_status fg
                LEFT JOIN log_check_mockup s ON s.created >= fg.mark_time
                    AND s.order_id = fg.id AND s.index_number = fg.file_name_index_number
                GROUP BY fg.folder, fg.estimate_date, fg.file_name_order_code, fg.file_name_index_number, num_file
            )
            SELECT
                ? AS estimate_date,
                SUM(CASE
                    WHEN DATE(firsr_scan) IS NULL OR DATE(firsr_scan) >= ?
                    THEN num_file
                END) AS tong_viec
            FROM item_scan_status
        ";

        $bindings = array_merge(
            [$date, $date],
            $this->printerBindings($factory),
            [$date, $date, $date, $date],
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }
}
