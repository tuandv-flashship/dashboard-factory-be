<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Hourly metrics for DTG Print (dtg_printed_product-based).
 *
 * Handles 2 metric types:
 * - Productivity: COUNT(product_id, index_num)
 * - MachineProductivity: grouped by printed_by
 *
 * Source: docs/rpt_factory_ops_metrics_v4.sql
 * - Productivity: lines 801-811
 * - MachineProductivity: lines 1498-1510
 */
final class GetHourlyDtgPrintMetricsTask extends ParentTask
{
    use QueriesFplatform;

    public function run(
        string $startShift,
        string $endShift,
        HourlyMetricType $metric,
    ): array {
        $selectClause = match ($metric) {
            HourlyMetricType::Productivity =>
                'COUNT(CONCAT(product_id, index_num)) AS value',
            HourlyMetricType::MachineProductivity =>
                "printed_by,\n                COUNT(CONCAT(product_id, index_num)) AS value",
            default => throw new \InvalidArgumentException("DTG Print does not support {$metric->value}"),
        };

        $groupBy = match ($metric) {
            HourlyMetricType::MachineProductivity => 'GROUP BY date_hour, printed_by',
            default => 'GROUP BY date_hour',
        };

        $orderBy = match ($metric) {
            HourlyMetricType::MachineProductivity => 'ORDER BY date_hour, printed_by',
            default => 'ORDER BY date_hour',
        };

        $sql = "
            SELECT
                LEFT(CONVERT_TZ(printed_at, '+7:00', 'US/Central'), 13) AS date_hour,
                {$selectClause}
            FROM dtg_printed_product
            WHERE printed_at >= CONVERT_TZ(?, 'US/Central', '+7:00')
                AND printed_at <  CONVERT_TZ(?, 'US/Central', '+7:00')
                AND print_status = 1
            {$groupBy}
            {$orderBy}
        ";

        return $this->formatHourlyResults(
            $this->queryFplatformAll($sql, [$startShift, $endShift]),
            ['value']
        );
    }
}
