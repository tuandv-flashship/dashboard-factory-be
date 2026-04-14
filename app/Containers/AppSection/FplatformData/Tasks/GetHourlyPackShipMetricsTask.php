<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Hourly metrics for Pack & Ship team (scan_label_history-based).
 *
 * Handles 3 metric types:
 * - Productivity: COUNT(DISTINCT barcode, index_num)
 * - StaffCount: COUNT(DISTINCT user_id)
 * - StaffProductivity: grouped by username
 *
 * Note: FLS uses INNER JOIN (inclusive), PD uses LEFT JOIN + exclusion (NOT IN FLS printers).
 *
 * Source: docs/rpt_factory_ops_metrics_v4.sql
 * - Productivity: lines 1020-1082
 * - StaffCount: lines 1235-1308
 * - StaffProductivity: lines 1735-1811
 */
final class GetHourlyPackShipMetricsTask extends ParentTask
{
    use QueriesFplatform;

    public function run(
        string $startShift,
        string $endShift,
        FactoryLine $factory,
        HourlyMetricType $metric,
    ): array {
        return $factory === FactoryLine::FLS
            ? $this->runFls($startShift, $endShift, $factory, $metric)
            : $this->runPd($startShift, $endShift, $metric);
    }

    /**
     * FLS: INNER JOIN with target printers (inclusive filter).
     */
    private function runFls(
        string $startShift,
        string $endShift,
        FactoryLine $factory,
        HourlyMetricType $metric,
    ): array {
        $extraUnions = $this->buildExtraPrinterUnions($factory);
        $selectClause = $this->buildSelect($metric, 'slh');
        $userJoin = $this->needsUserJoin($metric) ? 'JOIN user u ON u.id = slh.user_id' : '';
        $groupBy = $this->buildGroupBy($metric);

        $sql = "
            WITH
            target_printers AS (
                SELECT REPLACE(name, 'Machine ', 'May') AS printer_id
                FROM printer_manage
                WHERE factory = ?
                {$extraUnions}
            ),
            slh_filtered AS (
                SELECT
                    barcode COLLATE utf8mb4_0900_ai_ci AS barcode,
                    index_num,
                    user_id,
                    created_at
                FROM scan_label_history
                WHERE created_at >= CONVERT_TZ(?, 'US/Central', '+7:00')
                  AND created_at <  CONVERT_TZ(?, 'US/Central', '+7:00')
            )
            SELECT
                LEFT(CONVERT_TZ(slh.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
                {$selectClause}
            FROM slh_filtered slh
            JOIN order_check_file_dropbox ocfd
                ON ocfd.file_name_order_code = slh.barcode
                AND ocfd.status <> 2
                AND ocfd.folder_date BETWEEN DATE(?) - INTERVAL 20 DAY AND DATE(?)
            JOIN folder_manage fm
                ON ocfd.folder COLLATE utf8mb4_unicode_ci = fm.folder
                AND fm.status_folder <> 2
                AND fm.estimate_date BETWEEN DATE(?) - INTERVAL 20 DAY AND DATE(?)
            JOIN target_printers p
                ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
            {$userJoin}
            {$groupBy}
            ORDER BY 1" . ($metric === HourlyMetricType::StaffProductivity ? ', 2' : '') . "
        ";

        $bindings = array_merge(
            $this->printerBindings($factory),
            [$startShift, $endShift, $startShift, $startShift, $startShift, $startShift],
        );

        return $this->formatHourlyResults(
            $this->queryFplatformAll($sql, $bindings),
            $this->getIntFields($metric)
        );
    }

    /**
     * PD: LEFT JOIN + exclude FLS printers (everything that's NOT FLS).
     */
    private function runPd(
        string $startShift,
        string $endShift,
        HourlyMetricType $metric,
    ): array {
        $selectClause = $this->buildSelect($metric, 'slh');
        $userJoin = $this->needsUserJoin($metric) ? 'JOIN user u ON u.id = slh.user_id' : '';
        $groupBy = $this->buildGroupBy($metric);

        $sql = "
            WITH fls_printers AS (
                SELECT REPLACE(NAME, 'Machine ', 'May') COLLATE utf8mb4_unicode_ci AS machine_name
                FROM printer_manage
                WHERE factory = 'FLS'
                UNION SELECT 'MayHOTSHOT' COLLATE utf8mb4_unicode_ci
                UNION SELECT 'MayREPRINT' COLLATE utf8mb4_unicode_ci
            ),
            slh_filtered AS (
                SELECT
                    barcode COLLATE utf8mb4_0900_ai_ci AS barcode,
                    index_num,
                    user_id,
                    created_at
                FROM scan_label_history
                WHERE created_at >= CONVERT_TZ(?, 'US/Central', '+7:00')
                  AND created_at <  CONVERT_TZ(?, 'US/Central', '+7:00')
            )
            SELECT
                LEFT(CONVERT_TZ(slh.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
                {$selectClause}
            FROM slh_filtered slh
            LEFT JOIN order_check_file_dropbox ocfd
                ON ocfd.file_name_order_code = slh.barcode AND ocfd.status <> 2
                AND ocfd.folder_date BETWEEN DATE(?) - INTERVAL 20 DAY AND DATE(?)
            LEFT JOIN folder_manage fm
                ON ocfd.folder COLLATE utf8mb4_unicode_ci = fm.folder
                AND fm.status_folder <> 2
                AND fm.estimate_date BETWEEN DATE(?) - INTERVAL 20 DAY AND DATE(?)
            LEFT JOIN fls_printers fls
                ON COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) COLLATE utf8mb4_unicode_ci = fls.machine_name
            {$userJoin}
            WHERE fls.machine_name IS NULL
            {$groupBy}
            ORDER BY 1" . ($metric === HourlyMetricType::StaffProductivity ? ', 2' : '') . "
        ";

        $bindings = [$startShift, $endShift, $startShift, $startShift, $startShift, $startShift];

        return $this->formatHourlyResults(
            $this->queryFplatformAll($sql, $bindings),
            $this->getIntFields($metric)
        );
    }

    private function buildSelect(HourlyMetricType $metric, string $alias): string
    {
        return match ($metric) {
            HourlyMetricType::Productivity =>
                "COUNT(DISTINCT {$alias}.barcode, {$alias}.index_num) AS value",
            HourlyMetricType::StaffCount =>
                "COUNT(DISTINCT {$alias}.user_id) AS num_staff",
            HourlyMetricType::StaffProductivity =>
                "u.username,\n                COUNT(DISTINCT {$alias}.barcode, {$alias}.index_num) AS value",
            default => throw new \InvalidArgumentException("PackShip does not support {$metric->value}"),
        };
    }

    private function buildGroupBy(HourlyMetricType $metric): string
    {
        return match ($metric) {
            HourlyMetricType::StaffProductivity => 'GROUP BY 1, 2',
            default => 'GROUP BY date_hour',
        };
    }

    private function needsUserJoin(HourlyMetricType $metric): bool
    {
        return $metric === HourlyMetricType::StaffProductivity;
    }

    private function getIntFields(HourlyMetricType $metric): array
    {
        return match ($metric) {
            HourlyMetricType::StaffCount => ['num_staff'],
            default => ['value'],
        };
    }
}
