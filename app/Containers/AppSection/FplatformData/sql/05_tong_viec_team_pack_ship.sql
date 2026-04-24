-- ============================================================
-- @file    : 05_tong_viec_team_pack_ship.sql
-- @version : v2.0.0
-- @updated : 2026-04-24
-- @desc    : Lấy tổng việc team pack & ship (DTF1-FLS, DTF2-PD+DTG)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Refactor: total_per_date/file_groups/done_filtered/done_per_date CTE; PD adds DTG group
--   v2.0.0 (2026-04-24) - target_folders JOIN order_check_file_dropbox,
--                          add order_status CTE filtering orders table,
--                          total_per_date uses COUNT(*) instead of SUM(total_product),
--                          PD DTG adds JOIN orders
-- ============================================================

-- =========================================
-- Description: Lấy tổng việc team pack & ship
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
      AND COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
          SELECT REPLACE(name, 'Machine ', 'May')
          FROM fplatform.printer_manage
          WHERE factory = 'FLS'
          UNION ALL SELECT 'MayHOTSHOT'
          UNION ALL SELECT 'MayREPRINT'
      )
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
done_filtered AS (
    SELECT fg.estimate_date
    FROM order_status fg
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


-- PD
WITH target_folders_dtf AS (
    SELECT
        fm.folder COLLATE utf8mb4_unicode_ci AS folder,
        fm.estimate_date,
        fm.printer_default,
        d.file_name_order_code COLLATE utf8mb4_unicode_ci AS file_name_order_code,
        d.file_name_index_number
    FROM fplatform.folder_manage fm
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = fm.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    WHERE fm.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND fm.status_folder <> 2
      AND COALESCE(fm.printer_share, fm.printer_run, fm.printer_default) IN (
          SELECT REPLACE(name, 'Machine ', 'May')
          FROM fplatform.printer_manage
          WHERE factory = 'PD'
          UNION ALL SELECT 'MayHOTSHOTPD'
          UNION ALL SELECT 'MayREPRINTPD'
      )
    GROUP BY fm.estimate_date, fm.folder, fm.printer_default, d.file_name_order_code, d.file_name_index_number
),
target_folders_dtg AS (
    SELECT
        fm.folder_key AS folder,
        fm.estimate_folder_date AS estimate_date,
        IF(fm.folder_key LIKE 'REPRINT%', 'REPRINT', NULL) AS printer_default,
        d.order_code AS file_name_order_code,
        d.index_num AS file_name_index_number
    FROM fplatform.dtg_folder_detail fm
    JOIN fplatform.dtg_item_detail d
        ON d.folder_key = fm.folder_key
        AND d.active = 1
    WHERE fm.estimate_folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
    GROUP BY 1, 2, 3, 4, 5
),
printdash AS (
    SELECT * FROM target_folders_dtf
    UNION ALL
    SELECT * FROM target_folders_dtg
),
order_status AS (
    SELECT tf.*
    FROM printdash tf
    JOIN orders o ON o.order_code = tf.file_name_order_code
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY
                          AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
),
item_scan_status AS (
    SELECT
        CASE
            WHEN fg.printer_default IN ('MayHOTSHOT', 'MayREPRINT') THEN
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
)
SELECT
    ':estimate_date' AS estimate_date,
    SUM(IF(ngay_lam IS NULL OR ngay_lam >= ':estimate_date', 1, 0)) AS tong_viec
FROM item_scan_status;
