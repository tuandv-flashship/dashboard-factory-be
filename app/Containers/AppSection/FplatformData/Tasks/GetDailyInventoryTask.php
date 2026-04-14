<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\WorkType;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;

/**
 * Get daily inventory (tồn đầu ngày / tồn cuối ngày) for team IN or CẮT.
 *
 * Source: docs/ton_dau_ngay_update.sql lines 7-71
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
            WITH daily_stats AS (
                SELECT
                    f.estimate_date,
                    SUM(IF(s.work_status IS NULL, f.total_file, 0)) AS not_done,
                    SUM(f.total_file) AS total_file
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
                GROUP BY f.estimate_date
            )
            SELECT estimate_date, ton_dau, ton_cuoi
            FROM (
                SELECT
                    estimate_date,
                    total_file + COALESCE(SUM(not_done) OVER (
                        ORDER BY estimate_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS ton_dau,
                    SUM(not_done) OVER (ORDER BY estimate_date) AS ton_cuoi
                FROM daily_stats
            ) c
            WHERE estimate_date = ?
        ";

        $bindings = array_merge(
            [$workType->value, $workType->doneStatus(), $date, $date],
            $this->printerBindings($factory),
            [$date],
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }
}
