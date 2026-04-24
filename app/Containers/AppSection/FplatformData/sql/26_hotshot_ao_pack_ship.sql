-- ============================================================
-- @file    : 26_hotshot_ao_pack_ship.sql
-- @version : v2.0.0
-- @updated : 2026-04-24
-- @desc    : Lấy số áo hotshot - team pack & ship (DTF1-FLS, DTF2-PD)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Refactor: folder_printer/file_groups/item_status/aggregated_status CTE
--   v2.0.0 (2026-04-24) - target_folders JOIN order_check_file_dropbox,
--                          add order_status CTE filtering orders table,
--                          total_per_date uses COUNT(*) instead of SUM(total_product)
-- ============================================================

-- =========================================
-- Description: Lấy số áo hotshot (team pack & ship)
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS
WITH target_folders AS (
    SELECT
        fm.folder,
        fm.estimate_date,
        fm.printer_default,
        d.file_name_order_code,
        d.file_name_index_number
    FROM fplatform.folder_manage fm
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND fm.printer_default = 'MayHOTSHOT'
    GROUP BY fm.estimate_date, fm.folder, fm.printer_default, d.file_name_order_code, d.file_name_index_number
),
order_status AS (
    SELECT tf.*
    FROM target_folders tf
    JOIN orders o ON o.order_code = tf.file_name_order_code
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
),
total_per_date AS (
    SELECT estimate_date, COUNT(*) AS total_product
    FROM order_status
    GROUP BY estimate_date
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
    FROM order_status fg
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
WITH target_folders AS (
    SELECT
        fm.folder,
        fm.estimate_date,
        fm.printer_default,
        d.file_name_order_code,
        d.file_name_index_number
    FROM fplatform.folder_manage fm
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND fm.printer_default = 'MayHOTSHOTPD'
    GROUP BY fm.estimate_date, fm.folder, fm.printer_default, d.file_name_order_code, d.file_name_index_number
),
order_status AS (
    SELECT tf.*
    FROM target_folders tf
    JOIN orders o ON o.order_code = tf.file_name_order_code
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
),
total_per_date AS (
    SELECT estimate_date, COUNT(*) AS total_product
    FROM order_status
    GROUP BY estimate_date
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
    FROM order_status fg
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
