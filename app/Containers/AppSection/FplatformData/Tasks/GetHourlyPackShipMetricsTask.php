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
 * Note:
 * - FLS uses INNER JOIN (inclusive filter).
 * - PD Productivity uses new CTE structure (DTF + DTG union) per SQL v3.
 * - PD StaffCount/StaffProductivity still uses LEFT JOIN + exclusion (NOT IN FLS printers).
 *
 * Source: FplatformData/sql/11_hieu_suat_gio_pack_ship.sql (v3.0.0)
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
        if ($factory === FactoryLine::FLS) {
            return $this->runFls($startShift, $endShift, $factory, $metric);
        }

        // PD: Productivity uses new CTE structure, others use old exclusion logic
        if ($metric === HourlyMetricType::Productivity) {
            return $this->runPdProductivity($startShift, $endShift, $factory);
        }

        return $this->runPdLegacy($startShift, $endShift, $metric);
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
     * PD Productivity: new CTE structure (DTF + DTG union) per SQL v3.
     */
    private function runPdProductivity(
        string $startShift,
        string $endShift,
        FactoryLine $factory,
    ): array {
        $extraUnions = $this->buildExtraPrinterUnions($factory);

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
                    DATE_FORMAT(CONVERT_TZ(created_at, '+07:00', 'US/Central'), '%Y-%m-%d %H') AS date_hour
                FROM scan_label_history
                WHERE created_at >= CONVERT_TZ(?, 'US/Central', '+07:00')
                  AND created_at <  CONVERT_TZ(?, 'US/Central', '+07:00')
            ),
            dtf AS (
                SELECT
                    slh.date_hour,
                    COUNT(DISTINCT slh.barcode, slh.index_num) AS sum_shirt
                FROM slh_filtered slh
                JOIN order_check_file_dropbox ocfd
                    ON ocfd.file_name_order_code = slh.barcode
                    AND ocfd.status <> 2
                    AND ocfd.folder_date >= DATE(?) - INTERVAL 20 DAY
                    AND ocfd.folder_date <= DATE(?)
                JOIN folder_manage fm
                    ON fm.folder = ocfd.folder COLLATE utf8mb4_unicode_ci
                    AND fm.status_folder <> 2
                    AND fm.estimate_date >= DATE(?) - INTERVAL 20 DAY
                    AND fm.estimate_date <= DATE(?)
                WHERE COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
                    SELECT printer_id FROM target_printers
                )
                GROUP BY slh.date_hour
            ),
            dtg AS (
                SELECT
                    slh.date_hour,
                    COUNT(DISTINCT slh.barcode, slh.index_num) AS sum_shirt
                FROM slh_filtered slh
                JOIN dtg_item_detail dtg_det
                    ON dtg_det.order_code = slh.barcode
                    AND dtg_det.active = 1
                    AND dtg_det.folder_date >= DATE(?) - INTERVAL 20 DAY
                    AND dtg_det.folder_date <= DATE(?)
                GROUP BY slh.date_hour
            )
            SELECT
                date_hour,
                SUM(sum_shirt) AS value
            FROM (
                SELECT * FROM dtf
                UNION ALL
                SELECT * FROM dtg
            ) f
            GROUP BY date_hour
            ORDER BY date_hour
        ";

        $bindings = array_merge(
            $this->printerBindings($factory),
            [
                $startShift, $endShift,
                $startShift, $startShift, $startShift, $startShift,
                $startShift, $startShift,
            ],
        );

        return $this->formatHourlyResults(
            $this->queryFplatformAll($sql, $bindings),
            ['value']
        );
    }

    /**
     * PD legacy: LEFT JOIN + exclude FLS printers (for StaffCount/StaffProductivity).
     */
    private function runPdLegacy(
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
