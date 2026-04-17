<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\WorkType;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hotshot inventory (tổng việc & đã làm) for teams In, Pick, Cắt.
 *
 * Source: rpt_factory_ops_metrics_v8_1.sql
 * - Hotshot file team in: lines 1781-1845
 * - Hotshot áo team pick: lines 1849-1915
 * - Hotshot file team cắt: lines 1917-1981
 *
 * Filters by printer_default = MayHOTSHOT / MayHOTSHOTPD
 * instead of COALESCE(...) IN (...).
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

        // Pick uses total_product; In/Cắt use total_file
        $metricColumn = $workType === WorkType::Pick ? 'total_product' : 'total_file';
        $notDoneAlias = $workType === WorkType::Pick ? 'chua_pick' : 'chua_lam';

        // Pick needs extra copy_job = 0 filter
        $extraJoinCondition = $workType === WorkType::Pick ? 'AND s.copy_job = 0' : '';

        $sql = "
            WITH daily_stats AS (
                SELECT
                    f.estimate_date,
                    SUM(IF(s.work_status IS NULL, f.{$metricColumn}, 0)) AS {$notDoneAlias},
                    SUM(f.{$metricColumn}) AS total_metric
                FROM folder_manage f
                LEFT JOIN user_group_scan s
                    ON f.folder_code = s.folder_code
                    AND s.work_type = ?
                    AND s.work_status = ?
                    {$extraJoinCondition}
                WHERE f.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND f.status_folder <> 2
                    AND f.printer_default = ?
                GROUP BY f.estimate_date
            )
            SELECT estimate_date,
                tong_viec, tong_viec - con_lai AS da_lam
            FROM (
                SELECT
                    estimate_date,
                    total_metric + COALESCE(SUM({$notDoneAlias}) OVER (
                        ORDER BY estimate_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS tong_viec,
                    SUM({$notDoneAlias}) OVER (ORDER BY estimate_date) AS con_lai
                FROM daily_stats
            ) c
            WHERE estimate_date = ?
        ";

        $bindings = [
            $workType->value,
            $workType->doneStatus(),
            $date, $date,
            $hotshotPrinter,
            $date,
        ];

        return $this->formatHotshotResult($this->queryFplatform($sql, $bindings));
    }

    private function formatHotshotResult(?object $result): ?array
    {
        if (!$result) {
            return null;
        }

        return [
            'estimate_date' => $result->estimate_date,
            'tong_viec'     => (int) $result->tong_viec,
            'da_lam'        => (int) $result->da_lam,
        ];
    }
}
