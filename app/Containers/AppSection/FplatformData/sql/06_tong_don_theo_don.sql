-- ============================================================
-- @file    : 06_tong_don_theo_don.sql
-- @version : v2.0.0
-- @updated : 2026-04-24
-- @desc    : Lấy tổng việc & đã làm theo đơn (DTF1-FLS, DTF2-PD, DTG)
-- ------------------------------------------------------------
-- Changelog:
--   v1.0.0 (2026-04-21) - Initial version (split from rpt_factory_ops_metrics_v8_1.sql)
--   v1.1.0 (2026-04-21) - Refactor: target_items/item_status CTE; output chua_lam+da_lam thay tong_don
--   v2.0.0 (2026-04-24) - Add order_status CTE filtering orders table between target_items and item_status,
--                          DTG adds JOIN orders by order_code
-- ============================================================

-- =========================================
-- Description: Lấy tổng việc theo đơn
-- =========================================
-- Parameters:
-- :estimate_date (date) - ngày estimate

-- DTF1 - FLS
WITH target_items AS (
    SELECT
        f.estimate_date,
        f.folder,
        f.printer_default,
        d.file_name_order_code,
        d.file_name_index_number
    FROM fplatform.folder_manage f
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    WHERE f.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
          SELECT REPLACE(name, 'Machine ', 'May')
          FROM fplatform.printer_manage
          WHERE factory = 'FLS'
          UNION ALL SELECT 'MayHOTSHOT'
          UNION ALL SELECT 'MayREPRINT'
      )
    GROUP BY f.estimate_date, f.folder, f.printer_default, d.file_name_order_code, d.file_name_index_number
),
order_status AS (
    SELECT t.*
    FROM target_items t
    JOIN orders o ON o.order_code = t.file_name_order_code
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
),
item_status AS (
    SELECT
        ti.estimate_date,
        ti.file_name_order_code,
        CASE
            WHEN ti.printer_default IN ('MayHOTSHOT', 'MayREPRINT') THEN
                MIN(CASE WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= ti.estimate_date
                         THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) END)
            ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
        END AS ngay_lam
    FROM order_status ti
    LEFT JOIN fplatform.scan_label_history s
        ON s.barcode = ti.file_name_order_code COLLATE utf8mb4_0900_ai_ci
        AND s.index_num = ti.file_name_index_number
        AND s.created_at >= ':estimate_date' - INTERVAL 15 DAY
    GROUP BY ti.estimate_date, ti.folder, ti.printer_default, ti.file_name_order_code, ti.file_name_index_number
)
SELECT
    ':estimate_date' AS estimate_date,
    (
        SELECT COALESCE(SUM(c_lam), 0)
        FROM (
            SELECT COUNT(DISTINCT IF(ngay_lam IS NULL OR ngay_lam >= ':estimate_date', file_name_order_code, NULL)) AS c_lam
            FROM item_status
            GROUP BY estimate_date
        ) sub_chua
    ) AS tong_don,
    (
        SELECT COUNT(DISTINCT IF(ngay_lam = ':estimate_date', file_name_order_code, NULL))
        FROM item_status
    ) AS da_lam;


-- DTF2 - PD
WITH target_items AS (
    SELECT
        f.estimate_date,
        f.folder,
        f.printer_default,
        d.file_name_order_code,
        d.file_name_index_number
    FROM fplatform.folder_manage f
    JOIN fplatform.order_check_file_dropbox d
        ON d.folder = f.folder COLLATE utf8mb4_unicode_ci
        AND d.status <> 2
    WHERE f.estimate_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
      AND f.status_folder <> 2
      AND COALESCE(f.printer_share, f.printer_run, f.printer_default) IN (
          SELECT REPLACE(name, 'Machine ', 'May')
          FROM fplatform.printer_manage
          WHERE factory = 'PD'
          UNION ALL SELECT 'MayHOTSHOTPD'
          UNION ALL SELECT 'MayREPRINTPD'
      )
    GROUP BY f.estimate_date, f.folder, f.printer_default, d.file_name_order_code, d.file_name_index_number
),
order_status AS (
    SELECT t.*
    FROM target_items t
    JOIN orders o ON o.order_code = t.file_name_order_code
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
),
item_status AS (
    SELECT
        ti.estimate_date,
        ti.file_name_order_code,
        CASE
            WHEN ti.printer_default IN ('MayHOTSHOTPD', 'MayREPRINTPD') THEN
                MIN(CASE WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= ti.estimate_date
                         THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) END)
            ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
        END AS ngay_lam
    FROM order_status ti
    LEFT JOIN fplatform.scan_label_history s
        ON s.barcode = ti.file_name_order_code COLLATE utf8mb4_0900_ai_ci
        AND s.index_num = ti.file_name_index_number
        AND s.created_at >= ':estimate_date' - INTERVAL 15 DAY
    GROUP BY ti.estimate_date, ti.folder, ti.printer_default, ti.file_name_order_code, ti.file_name_index_number
)
SELECT
    ':estimate_date' AS estimate_date,
    (
        SELECT COALESCE(SUM(c_lam), 0)
        FROM (
            SELECT COUNT(DISTINCT IF(ngay_lam IS NULL OR ngay_lam >= ':estimate_date', file_name_order_code, NULL)) AS c_lam
            FROM item_status
            GROUP BY estimate_date
        ) sub_chua
    ) AS tong_don,
    (
        SELECT COUNT(DISTINCT IF(ngay_lam = ':estimate_date', file_name_order_code, NULL))
        FROM item_status
    ) AS da_lam;



-- DTG - PD
WITH target_items AS (
    SELECT
        estimate_folder_date AS estimate_date,
        folder_key AS folder,
        IF(folder_key LIKE 'REPRINT%', 'REPRINT', NULL) AS printer_default,
        order_code AS file_name_order_code,
        index_num AS file_name_index_number
    FROM fplatform.dtg_item_detail
    WHERE estimate_folder_date BETWEEN ':estimate_date' - INTERVAL 10 DAY AND ':estimate_date'
    AND active = 1
    GROUP BY estimate_folder_date, folder_key, order_code, index_num
),
order_status AS (
    SELECT t.*
    FROM target_items t
    JOIN orders o ON o.order_code = t.file_name_order_code
        AND o.created BETWEEN CONVERT_TZ(':estimate_date 00:00:00', 'US/Central', '+7:00') - INTERVAL 24 DAY AND CONVERT_TZ(':estimate_date 23:59:59', 'US/Central', '+7:00')
        AND o.status NOT IN ('HOLD','REQUEST_CANCEL','REJECTED','REJECT_REQUESTED','CANCELED')
),
item_status AS (
    SELECT
        ti.estimate_date,
        ti.file_name_order_code,
        CASE
            WHEN ti.printer_default = 'REPRINT' THEN
                MIN(CASE WHEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) >= ti.estimate_date
                         THEN DATE(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')) END)
            ELSE DATE(MIN(CONVERT_TZ(s.created_at, '+7:00', 'US/Central')))
        END AS ngay_lam
    FROM order_status ti
    LEFT JOIN fplatform.scan_label_history s
        ON s.barcode = ti.file_name_order_code
        AND s.index_num = ti.file_name_index_number
        AND s.created_at >= ':estimate_date' - INTERVAL 15 DAY
    GROUP BY ti.estimate_date, ti.folder, ti.printer_default, ti.file_name_order_code, ti.file_name_index_number
)
SELECT
    ':estimate_date' AS estimate_date,
    (
        SELECT COALESCE(SUM(c_lam), 0)
        FROM (
            SELECT COUNT(DISTINCT IF(ngay_lam IS NULL OR ngay_lam >= ':estimate_date', file_name_order_code, NULL)) AS c_lam
            FROM item_status
            GROUP BY estimate_date
        ) sub_chua
    ) AS tong_don,
    (
        SELECT COUNT(DISTINCT IF(ngay_lam = ':estimate_date', file_name_order_code, NULL))
        FROM item_status
    ) AS da_lam;
