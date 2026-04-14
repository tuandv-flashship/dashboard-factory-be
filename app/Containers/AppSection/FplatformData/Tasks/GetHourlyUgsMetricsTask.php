<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Enums\WorkType;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Hourly metrics for user_group_scan-based teams (IN, CẮT, PICK).
 *
 * Handles 4 metric types via a single optimized query builder:
 * - Productivity: SUM(total_file) or SUM(total_product_part)
 * - StaffCount: COUNT(DISTINCT user_id)
 * - StaffProductivity: grouped by username
 * - MachineProductivity: grouped by machine (IN only)
 *
 * Source: docs/rpt_factory_ops_metrics_v4.sql
 * - Productivity IN: lines 755-799, CẮT: 888-930, PICK: 822-864
 * - StaffCount IN/CẮT/PICK: lines 1086-1135
 * - StaffProductivity PICK: 1520-1566, CẮT: 1591-1637
 * - MachineProductivity: lines 1450-1496
 */
final class GetHourlyUgsMetricsTask extends ParentTask
{
    use QueriesFplatform;

    public function run(
        string $startShift,
        string $endShift,
        FactoryLine $factory,
        WorkType $workType,
        HourlyMetricType $metric,
    ): array {
        $extraUnions = $this->buildExtraPrinterUnions($factory);

        $selectClause = $this->buildSelectClause($workType, $metric);
        $groupByClause = $this->buildGroupByClause($metric);
        $orderByClause = $this->buildOrderByClause($metric);

        $sql = "
            SELECT
                LEFT(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'), 13) AS date_hour,
                {$selectClause}
            FROM user_group_scan s
            JOIN folder_manage f
                ON s.folder_code = f.folder_code
            " . ($this->needsUserJoin($metric) ? "JOIN user u ON s.user_id = u.id" : "") . "
            WHERE s.work_type = ?
                AND s.work_status = ?
                AND s.created_at >= CONVERT_TZ(?, 'US/Central', '+7:00')
                AND s.created_at <  CONVERT_TZ(?, 'US/Central', '+7:00')
                AND f.status_folder <> 2
                AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
                    SELECT REPLACE(name, 'Machine ', 'May')
                    FROM printer_manage
                    WHERE factory = ?
                    {$extraUnions}
                )
            {$groupByClause}
            {$orderByClause}
        ";

        $bindings = array_merge(
            [$workType->value, $workType->doneStatus(), $startShift, $endShift],
            $this->printerBindings($factory),
        );

        $intFields = $this->getIntFields($metric);

        return $this->formatHourlyResults(
            $this->queryFplatformAll($sql, $bindings),
            $intFields
        );
    }

    private function buildSelectClause(WorkType $workType, HourlyMetricType $metric): string
    {
        return match ($metric) {
            HourlyMetricType::Productivity => match ($workType) {
                WorkType::In, WorkType::Cat => 'SUM(s.total_file) AS value',
                WorkType::Pick => 'SUM(IF(s.total_product_part IS NULL, s.total_product, s.total_product_part)) AS value',
            },
            HourlyMetricType::StaffCount => 'COUNT(DISTINCT s.user_id) AS num_staff',
            HourlyMetricType::StaffProductivity => match ($workType) {
                WorkType::Pick => "u.username,\n                SUM(IF(s.total_product_part IS NULL, s.total_product, s.total_product_part)) AS value",
                default => "u.username,\n                SUM(s.total_file) AS value",
            },
            HourlyMetricType::MachineProductivity =>
                "COALESCE(f.printer_share, f.printer_run, f.printer_default) AS machine,\n                SUM(s.total_file) AS value",
        };
    }

    private function buildGroupByClause(HourlyMetricType $metric): string
    {
        return match ($metric) {
            HourlyMetricType::Productivity, HourlyMetricType::StaffCount => 'GROUP BY date_hour',
            HourlyMetricType::StaffProductivity => 'GROUP BY date_hour, username',
            HourlyMetricType::MachineProductivity => 'GROUP BY date_hour, machine',
        };
    }

    private function buildOrderByClause(HourlyMetricType $metric): string
    {
        return match ($metric) {
            HourlyMetricType::Productivity, HourlyMetricType::StaffCount => 'ORDER BY date_hour',
            HourlyMetricType::StaffProductivity => 'ORDER BY date_hour, username',
            HourlyMetricType::MachineProductivity => 'ORDER BY date_hour, machine',
        };
    }

    private function needsUserJoin(HourlyMetricType $metric): bool
    {
        return $metric === HourlyMetricType::StaffProductivity;
    }

    private function getIntFields(HourlyMetricType $metric): array
    {
        return match ($metric) {
            HourlyMetricType::Productivity, HourlyMetricType::StaffProductivity,
            HourlyMetricType::MachineProductivity => ['value'],
            HourlyMetricType::StaffCount => ['num_staff'],
        };
    }
}
