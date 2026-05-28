<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\WorkType;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hotshot inventory (tổng việc & đã làm) for teams In, Pick, Cắt.
 *
 * Source: FplatformData/sql/22_hotshot_file_team_in.sql (v2.0.0)
 *         FplatformData/sql/23_hotshot_ao_team_pick.sql (v2.0.0)
 *         FplatformData/sql/24_hotshot_file_team_cat.sql (v2.0.0)
 *
 * Logic: target_folders (JOIN order_check_file_dropbox) → order_status (JOIN orders) →
 *        LEFT JOIN user_group_scan → SUM(IF(...)) for tong_viec + da_lam.
 */
final class GetHotshotInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory, WorkType $workType): ?array
    {
        $hotshotPrinter = match ($factory) {
            FactoryLine::FLS => 'MayHOTSHOT',
            FactoryLine::PD  => 'MayHOTSHOTPD',
        };

        // In/Cắt count num_file, Pick counts 1 per item
        $metricExpr = $workType === WorkType::Pick ? '1' : 'o.num_file';

        // Pick and Cắt add copy_job = 0
        $extraScanCondition = in_array($workType, [WorkType::Pick, WorkType::Cat], true)
            ? 'AND s.copy_job = 0'
            : '';

        // Pick adds created_at interval filter
        $extraIntervalCondition = $workType === WorkType::Pick
            ? "AND s.created_at > CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00') - INTERVAL 12 DAY"
            : '';

        // In uses work_status=1, Pick/Cắt use work_status=0
        $workStatus = $workType === WorkType::In ? 1 : 0;

        // Pick doesn't need num_file in target_folders
        $numFileSelect = $workType === WorkType::Pick ? '' : ', COUNT(*) AS num_file';

        $sql = "
            WITH
            target_folders AS (
                SELECT
                    fm.folder,
                    fm.estimate_date,
                    d.file_name_order_code,
                    d.file_name_index_number
                    {$numFileSelect}
                FROM folder_manage fm
                JOIN order_check_file_dropbox d
                    ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
                    AND d.status <> 2
                WHERE fm.estimate_date BETWEEN ? - INTERVAL 9 DAY AND ?
                    AND fm.status_folder <> 2
                    AND fm.printer_default = ?
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
                    {$metricExpr}, 0
                )) AS tong_viec,
                SUM(IF(
                    s.created_at >= CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00')
                    AND s.created_at <= CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00'),
                    {$metricExpr}, 0
                )) AS da_lam
            FROM order_status o
            LEFT JOIN user_group_scan s ON s.folder = o.folder
                {$extraIntervalCondition}
                {$extraScanCondition}
                AND s.work_type = ?
                AND s.work_status = ?
        ";

        $bindings = [$date, $date, $hotshotPrinter, $date, $date, $date, $date, $date, $date];

        // Pick has an extra binding for the INTERVAL condition
        if ($workType === WorkType::Pick) {
            $bindings[] = $date; // for created_at > ... - INTERVAL 12 DAY
        }

        $bindings[] = $workType->value;
        $bindings[] = $workStatus;

        return $this->formatHotshotResult($this->queryFplatform($sql, $bindings));
    }
}
