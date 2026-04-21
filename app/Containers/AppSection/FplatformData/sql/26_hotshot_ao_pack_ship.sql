-- ============================================================
-- @file    : 26_hotshot_ao_pack_ship.sql
-- @version : v1.1.0
-- @updated : 2026-04-21
-- @desc    : Lấy số áo hotshot - team pack & ship (DTF1-FLS, DTF2-PD)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Refactor: folder_printer/file_groups/item_status/aggregated_status CTE
-- ============================================================

-- =========================================
-- Description: Lấy số áo hotshot (team pack & ship)
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS
WITH folder_printer AS (
    SELECT fm.estimate_date, fm.folder, fm.total_product, fm.printer_default
    FROM fplatform.folder_manage fm
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND fm.printer_default = 'MayHOTSHOT'
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
item_status AS (
    SELECT
        fg.estimate_date,
        CASE
            WHEN fg.printer_default = 'MayHOTSHOT' THEN
                MIN(CASE WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= fg.estimate_date
                         THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) END)
            ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
        END AS ngay_lam
    FROM file_groups fg
    LEFT JOIN fplatform.scan_label_history s
        ON s.barcode = fg.file_name_order_code COLLATE utf8mb4_0900_ai_ci
        AND s.index_num = fg.file_name_index_number
        AND s.created_at >= ':estimate_date' - INTERVAL 15 DAY
    GROUP BY fg.estimate_date, fg.folder, fg.printer_default, fg.file_name_order_code, fg.file_name_index_number
),
aggregated_status AS (
    SELECT
        estimate_date,
        SUM(IF(ngay_lam < ':estimate_date', 1, 0)) AS done_before,
        SUM(IF(ngay_lam = ':estimate_date', 1, 0)) AS done_today
    FROM item_status
    GROUP BY estimate_date
)
SELECT
    ':estimate_date' AS estimate_date,
    SUM(t.total_product - COALESCE(a.done_before, 0)) AS tong_viec,
    SUM(COALESCE(a.done_today, 0)) AS da_lam
FROM total_per_date t
LEFT JOIN aggregated_status a ON t.estimate_date = a.estimate_date;


-- DTF2 - PD
WITH folder_printer AS (
    SELECT fm.estimate_date, fm.folder, fm.total_product, fm.printer_default
    FROM fplatform.folder_manage fm
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND fm.printer_default = 'MayHOTSHOTPD'
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
item_status AS (
    SELECT
        fg.estimate_date,
        CASE
            WHEN fg.printer_default = 'MayHOTSHOTPD' THEN
                MIN(CASE WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= fg.estimate_date
                         THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) END)
            ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
        END AS ngay_lam
    FROM file_groups fg
    LEFT JOIN fplatform.scan_label_history s
        ON s.barcode = fg.file_name_order_code COLLATE utf8mb4_0900_ai_ci
        AND s.index_num = fg.file_name_index_number
        AND s.created_at >= ':estimate_date' - INTERVAL 15 DAY
    GROUP BY fg.estimate_date, fg.folder, fg.printer_default, fg.file_name_order_code, fg.file_name_index_number
),
aggregated_status AS (
    SELECT
        estimate_date,
        SUM(IF(ngay_lam < ':estimate_date', 1, 0)) AS done_before,
        SUM(IF(ngay_lam = ':estimate_date', 1, 0)) AS done_today
    FROM item_status
    GROUP BY estimate_date
)
SELECT
    ':estimate_date' AS estimate_date,
    SUM(t.total_product - COALESCE(a.done_before, 0)) AS tong_viec,
    SUM(COALESCE(a.done_today, 0)) AS da_lam
FROM total_per_date t
LEFT JOIN aggregated_status a ON t.estimate_date = a.estimate_date;
