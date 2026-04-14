<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Hourly metrics for Mockup team (log_check_mockup-based).
 *
 * Handles 3 metric types:
 * - Productivity: COUNT(DISTINCT barcode, index_number, side)
 * - StaffCount: COUNT(DISTINCT user_id)
 * - StaffProductivity: grouped by username
 *
 * Source: docs/rpt_factory_ops_metrics_v4.sql
 * - Productivity: lines 941-1009
 * - StaffCount: lines 1146-1224
 * - StaffProductivity: lines 1648-1724
 */
final class GetHourlyMockupMetricsTask extends ParentTask
{
    use QueriesFplatform;

    public function run(
        string $startShift,
        string $endShift,
        FactoryLine $factory,
        HourlyMetricType $metric,
    ): array {
        $extraUnions = $this->buildExtraPrinterUnions($factory);
        $selectClause = $this->buildSelect($metric);
        $extraJoin = $metric === HourlyMetricType::StaffProductivity
            ? 'JOIN user u ON u.id = lcm.user_id'
            : '';
        $groupBy = $this->buildGroupBy($metric);

        $sql = "
            WITH target_printers AS (
                SELECT REPLACE(name, 'Machine ', 'May') AS printer_id
                FROM printer_manage
                WHERE factory = ?
                {$extraUnions}
            ),
            lcm_filtered AS (
                SELECT
                    barcode COLLATE utf8mb4_0900_ai_ci AS barcode_fixed,
                    index_number,
                    user_id,
                    LEFT(CONVERT_TZ(created, '+7:00', 'US/Central'), 13) AS hour_us
                FROM log_check_mockup
                WHERE created >= CONVERT_TZ(?, 'US/Central', '+7:00')
                  AND created <  CONVERT_TZ(?, 'US/Central', '+7:00')
            )
            SELECT
                lcm.hour_us AS date_hour,
                {$selectClause}
            FROM lcm_filtered lcm
            JOIN order_check_file_dropbox ocfd
                ON ocfd.file_name_order_code = lcm.barcode_fixed
                AND ocfd.file_name_index_number = lcm.index_number
                AND ocfd.status <> 2
                AND ocfd.folder_date BETWEEN DATE(?) - INTERVAL 20 DAY AND DATE(?)
            JOIN folder_manage fm
                ON fm.folder = ocfd.folder COLLATE utf8mb4_unicode_ci
                AND fm.status_folder <> 2
                AND fm.estimate_date BETWEEN DATE(?) - INTERVAL 20 DAY AND DATE(?)
            JOIN target_printers p
                ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
            {$extraJoin}
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

    private function buildSelect(HourlyMetricType $metric): string
    {
        return match ($metric) {
            HourlyMetricType::Productivity =>
                'COUNT(DISTINCT lcm.barcode_fixed, ocfd.file_name_index_number, ocfd.file_name_side) AS value',
            HourlyMetricType::StaffCount =>
                'COUNT(DISTINCT lcm.user_id) AS num_staff',
            HourlyMetricType::StaffProductivity =>
                "u.username,\n                COUNT(DISTINCT lcm.barcode_fixed, ocfd.file_name_index_number, ocfd.file_name_side) AS value",
            default => throw new \InvalidArgumentException("Mockup does not support {$metric->value}"),
        };
    }

    private function buildGroupBy(HourlyMetricType $metric): string
    {
        return match ($metric) {
            HourlyMetricType::StaffProductivity => 'GROUP BY 1, 2',
            default => 'GROUP BY 1',
        };
    }

    private function getIntFields(HourlyMetricType $metric): array
    {
        return match ($metric) {
            HourlyMetricType::StaffCount => ['num_staff'],
            default => ['value'],
        };
    }
}
