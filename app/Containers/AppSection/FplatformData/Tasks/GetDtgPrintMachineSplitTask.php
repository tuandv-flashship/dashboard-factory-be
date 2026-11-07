<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Support\DtgMachineSplitter;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory for team IN - DTG, split by machine productivity ratio.
 *
 * Source: docs/rpt_factory_ops_metrics_v5.sql (tong_viec query).
 * The per-machine split is proportional to kpi_per_hour of the ACTIVE machines of the
 * dtg_print department (machines table), keyed by machine code = strtolower(printed_by).
 */
final class GetDtgPrintMachineSplitTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date): ?array
    {
        $sql = "
            WITH daily_aggregated AS (
                SELECT
                    d.estimate_folder_date,
                    COUNT(*) AS total_file,
                    SUM(IF(p.print_status = 0 OR p.print_status IS NULL, 1, 0)) AS unprint_file
                FROM dtg_item_detail d
                LEFT JOIN dtg_printed_product p
                    ON d.order_code = p.order_code
                    AND d.index_num = p.index_num
                    AND d.distribute_id = p.distribute_id
                WHERE d.estimate_folder_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND d.active = 1
                GROUP BY d.estimate_folder_date
            ),
            base_data AS (
                SELECT
                    estimate_folder_date,
                    total_file + COALESCE(SUM(unprint_file) OVER (
                        ORDER BY estimate_folder_date
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ), 0) AS tong_viec
                FROM daily_aggregated
            )
            SELECT
                estimate_folder_date AS estimate_date,
                tong_viec
            FROM base_data
            WHERE estimate_folder_date = ?
        ";

        $result = $this->queryFplatform($sql, [$date, $date, $date]);

        if (!$result) {
            return null;
        }

        $tongViec = (int) $result->tong_viec;

        return [
            'estimate_date' => $result->estimate_date,
            'tong_viec'     => $tongViec,
            'machines'      => DtgMachineSplitter::split($tongViec, $this->activeMachineKpiByCode()),
        ];
    }

    /**
     * [strtolower(code) => kpi_per_hour] of active dtg_print machines, ordered by sort_order.
     *
     * @return array<string,int>
     */
    private function activeMachineKpiByCode(): array
    {
        return Machine::active()
            ->whereHas('department', fn ($q) => $q->where('code', 'dtg_print'))
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Machine $m) => [strtolower($m->code) => (int) $m->kpi_per_hour])
            ->all();
    }
}
