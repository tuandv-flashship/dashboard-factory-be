<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tồn đầu/cuối ngày) for team Pick (DTF).
 *
 * Source: docs/ton_dau_ngay_update.sql lines 81-145
 * Uses total_product, work_type=100, copy_job=0
 */
final class GetPickInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory): ?array
    {
        $extraUnions = $this->buildExtraPrinterUnions($factory);

        $sql = "
            WITH daily_stats AS (
                SELECT
                    f.estimate_date,
                    SUM(IF(s.work_status IS NULL, f.total_product, 0)) AS chua_pick,
                    SUM(f.total_product) AS total_product
                FROM folder_manage f
                LEFT JOIN user_group_scan s
                    ON f.folder_code = s.folder_code
                    AND s.work_type = 100
                    AND s.work_status = 1
                    AND s.copy_job = 0
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
                    total_product + COALESCE(SUM(chua_pick) OVER (
                        ORDER BY estimate_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS ton_dau,
                    SUM(chua_pick) OVER (ORDER BY estimate_date) AS ton_cuoi
                FROM daily_stats
            ) c
            WHERE estimate_date = ?
        ";

        $bindings = array_merge(
            [$date, $date],
            $this->printerBindings($factory),
            [$date],
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }
}
