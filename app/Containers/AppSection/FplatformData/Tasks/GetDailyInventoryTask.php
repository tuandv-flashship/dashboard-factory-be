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
 * Source: FplatformData/sql/01_tong_viec_team_in.sql (v1.1.0)
 *         FplatformData/sql/03_tong_viec_team_cat.sql (v1.1.0)
 *
 * Logic: Flat IF — sum total_file where scan has not occurred yet
 *        OR occurred on/after the estimate date boundary (US/Central midnight).
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

        $sql = "
            SELECT
                ? AS estimate_date,
                SUM(IF(
                    s.created_at IS NULL
                    OR s.created_at >= CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00'),
                    f.total_file, 0
                )) AS tong_viec
            FROM folder_manage f
            LEFT JOIN user_group_scan s
                ON f.folder_code = s.folder_code
                AND s.work_type = ?
                AND s.work_status = ?
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
            [$date, $date, $workType->value, $workType->doneStatus(), $date, $date],
            $this->printerBindings($factory),
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }
}
