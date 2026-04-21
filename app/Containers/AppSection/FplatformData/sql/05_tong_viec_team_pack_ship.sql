-- ============================================================
-- @file    : 05_tong_viec_team_pack_ship.sql
-- @version : v1.1.0
-- @updated : 2026-04-21
-- @desc    : Lấy tổng việc team pack & ship (DTF1-FLS, DTF2-PD+DTG)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Refactor: total_per_date/file_groups/done_filtered/done_per_date CTE; PD adds DTG group
-- ============================================================

-- =========================================
-- Description: Lấy tổng việc team pack & ship
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id
    FROM fplatform.printer_manage
    WHERE factory = 'FLS'
    UNION ALL SELECT 'MayHOTSHOT'
    UNION ALL SELECT 'MayREPRINT'
),
folder_printer AS (
    SELECT fm.estimate_date, fm.folder, fm.total_product, fm.printer_default
    FROM fplatform.folder_manage fm
    JOIN target_printers p ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
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
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    GROUP BY f.estimate_date, f.folder, f.printer_default, d.file_name_order_code, d.file_name_index_number
),
done_filtered AS (
    SELECT fg.estimate_date
    FROM file_groups fg
    LEFT JOIN fplatform.scan_label_history s
        ON s.barcode = fg.file_name_order_code COLLATE utf8mb4_0900_ai_ci
        AND s.index_num = fg.file_name_index_number
        AND s.created_at >= ':estimate_date' - INTERVAL 15 DAY
    GROUP BY fg.estimate_date, fg.folder, fg.printer_default, fg.file_name_order_code, fg.file_name_index_number
    HAVING
        CASE
            WHEN fg.printer_default IN ('MayHOTSHOT', 'MayREPRINT') THEN
                MIN(CASE WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= fg.estimate_date
                         THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) END)
            ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
        END < ':estimate_date'
),
done_per_date AS (
    SELECT estimate_date, COUNT(*) AS da_lam
    FROM done_filtered
    GROUP BY estimate_date
)
SELECT
    ':estimate_date' AS estimate_date,
    SUM(t.total_product - COALESCE(d.da_lam, 0)) AS tong_viec
FROM total_per_date t
LEFT JOIN done_per_date d ON t.estimate_date = d.estimate_date;


-- PD (DTF + DTG)
WITH target_printers AS (
    SELECT REPLACE(name, 'Machine ', 'May') AS printer_id
    FROM fplatform.printer_manage
    WHERE factory = 'PD'
    UNION ALL SELECT 'MayHOTSHOTPD'
    UNION ALL SELECT 'MayREPRINTPD'
),
folder_printer AS (
    SELECT fm.estimate_date, fm.folder, fm.total_product, fm.printer_default
    FROM fplatform.folder_manage fm
    JOIN target_printers p ON p.printer_id = COALESCE(fm.printer_share, fm.printer_run, fm.printer_default)
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
),
total_dtf AS (
    SELECT estimate_date, SUM(total_product) AS total_product
    FROM folder_printer
    GROUP BY estimate_date
),
done_dtf AS (
    SELECT estimate_date, COUNT(*) AS da_lam
    FROM (
        SELECT fg.estimate_date
        FROM folder_printer fg
        JOIN fplatform.order_check_file_dropbox d
            ON d.folder = fg.folder COLLATE utf8mb4_unicode_ci
            AND d.status <> 2
        LEFT JOIN fplatform.scan_label_history s
            ON s.barcode = d.file_name_order_code COLLATE utf8mb4_0900_ai_ci
            AND s.index_num = d.file_name_index_number
            AND s.created_at >= ':estimate_date' - INTERVAL 15 DAY
        GROUP BY fg.estimate_date, fg.folder, fg.printer_default, d.file_name_order_code, d.file_name_index_number
        HAVING
            CASE
                WHEN fg.printer_default IN ('MayHOTSHOTPD', 'MayREPRINTPD') THEN
                    MIN(CASE WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= fg.estimate_date
                             THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) END)
                ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
            END < ':estimate_date'
    ) filtered
    GROUP BY estimate_date
),
dtg_groups AS (
    SELECT
        estimate_folder_date AS estimate_date,
        folder_key AS folder,
        IF(folder_key LIKE 'REPRINT%', 'REPRINT', NULL) AS printer_default,
        order_code AS file_name_order_code,
        index_num AS file_name_index_number,
        COUNT(*) AS num_shirt
    FROM fplatform.dtg_item_detail
    WHERE estimate_folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
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
        LEFT JOIN fplatform.scan_label_history s
            ON s.barcode = fg.file_name_order_code
            AND s.index_num = fg.file_name_index_number
            AND s.created_at >= ':estimate_date' - INTERVAL 15 DAY
        GROUP BY fg.estimate_date, fg.folder, fg.printer_default, fg.file_name_order_code, fg.file_name_index_number, fg.num_shirt
        HAVING
            CASE
                WHEN fg.printer_default = 'REPRINT' THEN
                    MIN(CASE WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= fg.estimate_date
                             THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) END)
                ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
            END < ':estimate_date'
    ) filtered
    GROUP BY estimate_date
)
SELECT
    ':estimate_date' AS estimate_date,
    (
        SELECT COALESCE(SUM(t.total_product - COALESCE(d.da_lam, 0)), 0)
        FROM total_dtf t LEFT JOIN done_dtf d ON t.estimate_date = d.estimate_date
    ) + (
        SELECT COALESCE(SUM(t.total_shirt - COALESCE(d.da_lam, 0)), 0)
        FROM total_dtg t LEFT JOIN done_dtg d ON t.estimate_date = d.estimate_date
    ) AS tong_viec;
