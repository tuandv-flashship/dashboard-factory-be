<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\WorkType;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hotshot inventory (tổng việc & đã làm) for teams In, Pick, Cắt.
 *
 * Source: FplatformData/sql/22_hotshot_file_team_in.sql (v1.1.0)
 *         FplatformData/sql/23_hotshot_ao_team_pick.sql (v1.1.0)
 *         FplatformData/sql/24_hotshot_file_team_cat.sql (v1.1.0)
 *
 * Logic: Flat IF — sum metric where scan has not occurred yet OR
 *        occurred during the estimate date window (US/Central).
 *        da_lam = scans strictly within the estimate date.
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
        $metricColumn        = $workType === WorkType::Pick ? 'total_product' : 'total_file';
        $extraJoinCondition  = $workType === WorkType::Pick ? 'AND s.copy_job = 0' : '';

        $sql = "
            SELECT
                ? AS estimate_date,
                SUM(IF(
                    s.created_at IS NULL
                    OR s.created_at >= CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00'),
                    f.{$metricColumn}, 0
                )) AS tong_viec,
                SUM(IF(
                    s.created_at >= CONVERT_TZ(CONCAT(?, ' 00:00:00'), 'US/Central', '+7:00')
                    AND s.created_at <= CONVERT_TZ(CONCAT(?, ' 23:59:59'), 'US/Central', '+7:00'),
                    f.{$metricColumn}, 0
                )) AS da_lam
            FROM folder_manage f
            LEFT JOIN user_group_scan s
                ON f.folder_code = s.folder_code
                AND s.work_type = ?
                AND s.work_status = ?
                {$extraJoinCondition}
            WHERE f.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                AND f.status_folder <> 2
                AND f.printer_default = ?
        ";

        $bindings = [
            $date,
            $date, $date, $date,   // tong_viec boundary + da_lam boundary
            $workType->value,
            $workType->doneStatus(),
            $date, $date,
            $hotshotPrinter,
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
