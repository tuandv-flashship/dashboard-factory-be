<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Traits\QueriesFplatform;
use App\Ship\Parents\Tasks\Task as ParentTask;

/**
 * Get daily inventory (tổng việc) for team Pack & Ship (DTF).
 *
 * Source: FplatformData/sql/05_tong_viec_team_pack_ship.sql (v1.1.0)
 *
 * Logic: total_per_date / file_groups / done_filtered / done_per_date CTE.
 *        HOTSHOT/REPRINT printers use strict date >= estimate_date cutoff.
 *        PD factory includes DTG items (dtg_item_detail + scan_label_history).
 */
final class GetPackShipInventoryTask extends ParentTask
{
    use QueriesFplatform;

    public function run(string $date, FactoryLine $factory): ?array
    {
        return $factory === FactoryLine::PD
            ? $this->runPdWithDtg($date, $factory)
            : $this->runDtfOnly($date, $factory);
    }

    /**
     * FLS: DTF data only.
     */
    private function runDtfOnly(string $date, FactoryLine $factory): ?array
    {
        $extraUnions     = $this->buildExtraPrinterUnions($factory);
        $hotshotPrinters = $this->hotshotPrinterList($factory);

        $sql = "
            WITH
            target_printers AS (
                SELECT REPLACE(name, 'Machine ', 'May') AS printer_id
                FROM printer_manage
                WHERE factory = ?
                {$extraUnions}
            ),
            folder_printer AS (
                SELECT fm.estimate_date, fm.folder, fm.total_product, fm.printer_default
                FROM folder_manage fm
                JOIN target_printers p ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
                WHERE fm.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
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
            done_filtered AS (
                SELECT fg.estimate_date
                FROM file_groups fg
                LEFT JOIN scan_label_history s
                    ON s.barcode = fg.file_name_order_code COLLATE utf8mb4_0900_ai_ci
                    AND s.index_num = fg.file_name_index_number
                    AND s.created_at >= ? - INTERVAL 15 DAY
                GROUP BY fg.estimate_date, fg.folder, fg.printer_default, fg.file_name_order_code, fg.file_name_index_number
                HAVING
                    CASE
                        WHEN fg.printer_default IN ({$hotshotPrinters}) THEN
                            MIN(CASE
                                WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= fg.estimate_date
                                THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'))
                            END)
                        ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
                    END < ?
            ),
            done_per_date AS (
                SELECT estimate_date, COUNT(*) AS da_lam
                FROM done_filtered
                GROUP BY estimate_date
            )
            SELECT
                ? AS estimate_date,
                SUM(t.total_product - COALESCE(d.da_lam, 0)) AS tong_viec
            FROM total_per_date t
            LEFT JOIN done_per_date d ON t.estimate_date = d.estimate_date
        ";

        $bindings = array_merge(
            $this->printerBindings($factory),
            [$date, $date, $date, $date, $date],
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }

    /**
     * PD: DTF + DTG union.
     */
    private function runPdWithDtg(string $date, FactoryLine $factory): ?array
    {
        $extraUnions     = $this->buildExtraPrinterUnions($factory);
        $hotshotPrinters = $this->hotshotPrinterList($factory);

        $sql = "
            WITH
            target_printers AS (
                SELECT REPLACE(name, 'Machine ', 'May') COLLATE utf8mb4_unicode_ci AS printer_id
                FROM printer_manage
                WHERE factory = ?
                {$extraUnions}
            ),
            folder_printer AS (
                SELECT fm.estimate_date, fm.folder, fm.total_product, fm.printer_default
                FROM folder_manage fm
                JOIN target_printers p ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
                WHERE fm.estimate_date BETWEEN ? - INTERVAL 10 DAY AND ?
                    AND fm.status_folder <> 2
            ),
            total_dtf AS (
                SELECT estimate_date, SUM(total_product) AS total_product
                FROM folder_printer
                GROUP BY estimate_date
            ),
            dtf_groups AS (
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
            done_dtf AS (
                SELECT estimate_date, COUNT(*) AS da_lam
                FROM (
                    SELECT fg.estimate_date
                    FROM dtf_groups fg
                    LEFT JOIN scan_label_history s
                        ON s.barcode = fg.file_name_order_code COLLATE utf8mb4_0900_ai_ci
                        AND s.index_num = fg.file_name_index_number
                        AND s.created_at >= ? - INTERVAL 15 DAY
                    GROUP BY fg.estimate_date, fg.folder, fg.printer_default, fg.file_name_order_code, fg.file_name_index_number
                    HAVING
                        CASE
                            WHEN fg.printer_default IN ({$hotshotPrinters}) THEN
                                MIN(CASE
                                    WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= fg.estimate_date
                                    THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'))
                                END)
                            ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
                        END < ?
                ) df
                GROUP BY estimate_date
            ),
            dtg_groups AS (
                SELECT
                    estimate_folder_date AS estimate_date,
                    folder_key AS folder,
                    order_code AS file_name_order_code,
                    index_num AS file_name_index_number,
                    COUNT(*) AS num_shirt
                FROM dtg_item_detail
                WHERE estimate_folder_date BETWEEN ? - INTERVAL 10 DAY AND ?
                GROUP BY estimate_folder_date, folder_key, order_code, index_num
            ),
            total_dtg AS (
                SELECT estimate_date, SUM(num_shirt) AS total_shirt
                FROM dtg_groups
                GROUP BY estimate_date
            ),
            done_dtg AS (
                SELECT estimate_date, SUM(num_shirt) AS da_lam
                FROM (
                    SELECT fg.estimate_date, fg.num_shirt
                    FROM dtg_groups fg
                    LEFT JOIN scan_label_history s
                        ON s.barcode = fg.file_name_order_code
                        AND s.index_num = fg.file_name_index_number
                        AND s.created_at >= ? - INTERVAL 15 DAY
                    GROUP BY fg.estimate_date, fg.folder, fg.file_name_order_code, fg.file_name_index_number, fg.num_shirt
                    HAVING DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central'))) < ?
                ) dd
                GROUP BY estimate_date
            )
            SELECT
                ? AS estimate_date,
                (
                    SELECT COALESCE(SUM(t.total_product - COALESCE(d.da_lam, 0)), 0)
                    FROM total_dtf t LEFT JOIN done_dtf d ON t.estimate_date = d.estimate_date
                ) + (
                    SELECT COALESCE(SUM(t.total_shirt - COALESCE(d.da_lam, 0)), 0)
                    FROM total_dtg t LEFT JOIN done_dtg d ON t.estimate_date = d.estimate_date
                ) AS tong_viec
        ";

        $bindings = array_merge(
            $this->printerBindings($factory),
            [$date, $date, $date, $date, $date, $date, $date, $date, $date],
        );

        return $this->formatResult($this->queryFplatform($sql, $bindings));
    }

    /**
     * Comma-separated quoted hotshot printer names for IN clause.
     */
    private function hotshotPrinterList(FactoryLine $factory): string
    {
        return match ($factory) {
            FactoryLine::FLS => "'MayHOTSHOT', 'MayREPRINT'",
            FactoryLine::PD  => "'MayHOTSHOTPD', 'MayREPRINTPD'",
        };
    }
}
