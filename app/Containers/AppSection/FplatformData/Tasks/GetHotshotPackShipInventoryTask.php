<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get hotshot pack & ship inventory (tổng việc & đã làm — hotshot team pack & ship).
 *
 * Source: FplatformData/sql/26_hotshot_ao_pack_ship.sql (v1.1.0)
 *
 * Logic: folder_printer → total_per_date / file_groups / item_status / aggregated_status CTE.
 *        HOTSHOT printer uses strict date >= estimate_date cutoff (ngay_lam).
 *        tong_viec = sum(total_product - done_before); da_lam = sum(done_today).
 */
final class GetHotshotPackShipInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory): ?array
    {
        $hotshotPrinter = match ($factory) {
            FactoryLine::FLS => 'MayHOTSHOT',
            FactoryLine::PD  => 'MayHOTSHOTPD',
        };

        $sql = "
            WITH
            folder_printer AS (
                SELECT fm.estimate_date, fm.folder, fm.total_product, fm.printer_default
                FROM folder_manage fm
                WHERE fm.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND fm.printer_default = ?
                    AND fm.status_folder <> 2
            ),
            total_per_date AS (
                SELECT estimate_date, SUM(total_product) AS total_product
                FROM folder_printer
                GROUP BY estimate_date
            ),
            file_groups AS (
                SELECT
                    f.estimate_date,
                    f.folder,
                    f.printer_default,
                    d.file_name_order_code,
                    d.file_name_index_number
                FROM folder_printer f
                JOIN order_check_file_dropbox d
                    ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
                    AND d.status <> 2
                GROUP BY f.estimate_date, f.folder, f.printer_default, d.file_name_order_code, d.file_name_index_number
            ),
            item_status AS (
                SELECT
                    fg.estimate_date,
                    CASE
                        WHEN fg.printer_default = ?
                        THEN MIN(CASE
                                WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= fg.estimate_date
                                THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'))
                             END)
                        ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
                    END AS ngay_lam
                FROM file_groups fg
                LEFT JOIN scan_label_history s
                    ON s.barcode = fg.file_name_order_code COLLATE utf8mb4_0900_ai_ci
                    AND s.index_num = fg.file_name_index_number
                    AND s.created_at >= ? - INTERVAL 15 DAY
                GROUP BY fg.estimate_date, fg.folder, fg.printer_default, fg.file_name_order_code, fg.file_name_index_number
            ),
            aggregated_status AS (
                SELECT
                    estimate_date,
                    SUM(IF(ngay_lam < ?, 1, 0)) AS done_before,
                    SUM(IF(ngay_lam = ?, 1, 0)) AS done_today
                FROM item_status
                GROUP BY estimate_date
            )
            SELECT
                ? AS estimate_date,
                SUM(t.total_product - COALESCE(a.done_before, 0)) AS tong_viec,
                SUM(COALESCE(a.done_today, 0)) AS da_lam
            FROM total_per_date t
            LEFT JOIN aggregated_status a ON t.estimate_date = a.estimate_date
        ";

        $bindings = [$date, $date, $hotshotPrinter, $hotshotPrinter, $date, $date, $date, $date];

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
