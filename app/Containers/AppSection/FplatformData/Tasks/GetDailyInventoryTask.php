<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\WorkType;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;

/**
 * Get daily inventory (tổng việc) for team IN or CẮT.
 *
 * Source: FplatformData/sql/01_tong_viec_team_in.sql (v2.0.0)
 *         FplatformData/sql/03_tong_viec_team_cat.sql (v2.0.0)
 *
 * Logic: target_folders (JOIN order_check_file_dropbox) → order_status (JOIN orders) →
 *        LEFT JOIN user_group_scan → SUM(IF(...)) for tong_viec.
 */
final class GetDailyInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(
        Carbon|string $estimateDate,
        FactoryLine $factory,
        WorkType $workType = WorkType::In,
    ): ?array {
        $date = $estimateDate instanceof Carbon
            ? $estimateDate->toDateString()
            : $estimateDate;

        $extraUnions = $this->buildExtraPrinterUnions($factory);

        // Cắt adds copy_job = 0
        $extraScanCondition = $workType === WorkType::Cat ? 'AND s.copy_job = 0' : '';

        // In uses work_status=1, Cắt uses work_status=0
        $workStatus = $workType === WorkType::In ? 1 : 0;

        $sql = "
            WITH
            target_folders AS (
                SELECT
                    fm.folder,
                    fm.estimate_date,
                    d.file_name_order_code,
                    d.file_name_index_number,
                    COUNT(*) AS num_file
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
                    o.num_file, 0
                )) AS tong_viec
            FROM order_status o
            LEFT JOIN user_group_scan s ON s.folder = o.folder
                AND s.work_type = ?
                AND s.work_status = ?
                {$extraScanCondition}
        ";

        $bindings = array_merge(
            [$date, $date],
            $this->printerBindings($factory),
            [$date, $date, $date, $date, $workType->value, $workStatus],
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }
}
