<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get log file cut data grouped by user.
 *
 * Source: FplatformData/sql/27_log_file_cut_theo_user.sql (v3.0.0)
 *
 * Logic: user_group_scan → folder_manage → user.
 *        Filters by factory printers, work_status=0, work_type=2 (CẮT).
 *        Output: array of { username, created_at, total_file }
 */
final class GetLogFileCutTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $startLog, string $endLog, FactoryLine $factory): array
    {
        $extraUnions = $this->buildExtraPrinterUnions($factory);

        $sql = "
            SELECT
                u.username,
                CONVERT_TZ(s.created_at, '+7:00', 'US/Central') AS created_at,
                s.total_file
            FROM user_group_scan s
            JOIN folder_manage fm ON s.folder = fm.folder
            JOIN user u ON u.id = s.user_id
            WHERE s.created_at BETWEEN CONVERT_TZ(?, 'US/Central', '+7:00')
                                       AND CONVERT_TZ(?, 'US/Central', '+7:00')
                AND fm.status_folder <> 2
                AND COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
                    SELECT REPLACE(name, 'Machine ', 'May')
                    FROM printer_manage
                    WHERE factory = ?
                    {$extraUnions}
                )
                AND work_status = 0
                AND work_type = 2
        ";

        $bindings = array_merge(
            [$startLog, $endLog],
            $this->printerBindings($factory),
        );

        $rows = $this->queryFplatformAll($sql, $bindings);

        return array_map(function (object $row) {
            return [
                'username'   => $row->username,
                'created_at' => $row->created_at,
                'total_file' => (int) $row->total_file,
            ];
        }, $rows);
    }
}
