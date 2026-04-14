<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Hourly metrics for DTG Pick (dtg_folder_detail + dtg_item_detail-based).
 *
 * Handles 3 metric types:
 * - Productivity: COUNT(order_code)
 * - StaffCount: COUNT(DISTINCT done_by)
 * - StaffProductivity: grouped by done_user_name
 *
 * Source: docs/rpt_factory_ops_metrics_v4.sql
 * - Productivity: lines 866-877
 * - StaffCount: lines 1319-1329
 * - StaffProductivity: lines 1568-1580
 */
final class GetHourlyDtgPickMetricsTask extends ParentTask
{
    use QueriesFplatform;

    public function run(
        string $startShift,
        string $endShift,
        HourlyMetricType $metric,
    ): array {
        $selectClause = match ($metric) {
            HourlyMetricType::Productivity => 'COUNT(f.order_code) AS value',
            HourlyMetricType::StaffCount => 'COUNT(DISTINCT s.done_by) AS num_staff',
            HourlyMetricType::StaffProductivity =>
                "s.done_user_name AS username,\n                COUNT(f.order_code) AS value",
            default => throw new \InvalidArgumentException("DTG Pick does not support {$metric->value}"),
        };

        $groupBy = match ($metric) {
            HourlyMetricType::StaffProductivity => 'GROUP BY date_hour, done_user_name',
            default => 'GROUP BY 1',
        };

        $orderBy = match ($metric) {
            HourlyMetricType::StaffProductivity => 'ORDER BY date_hour, done_user_name',
            default => 'ORDER BY 1',
        };

        $sql = "
            SELECT
                LEFT(CONVERT_TZ(s.done_at, '+7:00', 'US/Central'), 13) AS date_hour,
                {$selectClause}
            FROM dtg_item_detail f
            JOIN dtg_folder_detail s
                ON s.folder_key = f.folder_key
            WHERE s.done_at >= CONVERT_TZ(?, 'US/Central', '+7:00')
                AND s.done_at <  CONVERT_TZ(?, 'US/Central', '+7:00')
            {$groupBy}
            {$orderBy}
        ";

        $intFields = $metric === HourlyMetricType::StaffCount ? ['num_staff'] : ['value'];

        return $this->formatHourlyResults(
            $this->queryFplatformAll($sql, [$startShift, $endShift]),
            $intFields
        );
    }
}
