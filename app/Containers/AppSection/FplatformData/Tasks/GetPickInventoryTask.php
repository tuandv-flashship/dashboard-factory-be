<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng việc) for team Pick (DTF).
 *
 * Source: FplatformData/sql/02_tong_viec_team_pick.sql (v1.1.0)
 *
 * Logic: Flat IF — sum total_product where pick scan has not occurred yet
 *        OR occurred on/after the estimate date boundary (US/Central midnight).
 */
final class GetPickInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory): ?array
    {
        $extraUnions = $this->buildExtraPrinterUnions($factory);

        $sql = "
            SELECT
                ? AS estimate_date,
                SUM(IF(
                    s.created_at IS NULL
                    OR s.created_at >= CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00'),
                    f.total_product, 0
                )) AS tong_viec
            FROM folder_manage f
            LEFT JOIN user_group_scan s
                ON f.folder_code = s.folder_code
                AND s.work_type = 100
                AND s.work_status = 0
                AND s.copy_job = 0
            WHERE f.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                AND f.status_folder <> 2
                AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
                    SELECT REPLACE(NAME, 'Machine ', 'May')
                    FROM printer_manage
                    WHERE factory = ?
                    {$extraUnions}
                )
        ";

        $bindings = array_merge(
            [$date, $date, $date, $date],
            $this->printerBindings($factory),
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }
}
